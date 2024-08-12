<?php

namespace Spatie\LaravelFlare;

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernelInterface;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\ViewException;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TickReceived;
use Monolog\Logger;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareProvider;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Support\BackTracer as BaseBackTracer;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Commands\TestCommand;
use Spatie\LaravelFlare\Http\Middleware\FlareTracingMiddleware;
use Spatie\LaravelFlare\Support\BackTracer;
use Spatie\LaravelFlare\Support\FlareLogHandler;
use Spatie\LaravelFlare\Support\Telemetry;
use Spatie\LaravelFlare\Views\ViewExceptionMapper;
use Spatie\LaravelFlare\Views\ViewFrameMapper;

class FlareServiceProvider extends ServiceProvider
{
    protected FlareProvider $provider;

    protected FlareConfig $config;

    public function register(): void
    {
        if (! $this->app->has(FlareConfig::class)) {
            $this->mergeConfigFrom(__DIR__.'/../config/flare.php', 'flare');

            $this->config = FlareConfig::fromLaravelConfig();

            $this->app->singleton(FlareConfig::class, fn () => $this->config);
        } else {
            $this->config = $this->app->make(FlareConfig::class);
        }

        $this->registerLogHandler();

        if ($this->config->apiToken === null) {
            return;
        }

        $this->provider = new FlareProvider(
            $this->config,
            $this->app,
            function (Container $container, string $recorderClass, array $config) {
                $this->app->singleton($recorderClass);
                $this->app->when($recorderClass)->needs('$config')->give($config);
            }
        );

        $this->provider->register();

        $this->app->singleton(BaseBackTracer::class, fn () => new BackTracer(
            $this->app->make(ViewFrameMapper::class),
            $this->config->applicationPath
        ));

        $this->app->singleton(ViewFrameMapper::class);

        $this->registerShareButton();

        if ($this->config->trace === false) {
            return;
        }

        $this->app->singleton(Resource::class, fn () => Resource::build(
            $this->config->applicationName,
            $this->config->applicationVersion,
            Telemetry::NAME,
            Telemetry::VERSION
        )->host());

        $this->app->singleton(FlareTracingMiddleware::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/flare.php' => config_path('flare.php'),
            ], 'flare-config');
        }

        if ($this->config->apiToken === null) {
            return;
        }

        $this->provider->boot();

        $this->configureTinker();
        $this->configureOctane();
        $this->registerViewExceptionMapper();
        $this->configureQueue();

        if ($this->config->trace === false) {
            return;
        }

        $this->prependTracingMiddleware();

        register_shutdown_function(function () {
            $this->app->make(Tracer::class)->transmit();
        });
    }

    protected function registerLogHandler(): void
    {
        $this->app->singleton('flare.logger', function ($app) {
            if ($this->config->apiToken === null || $this->config->sendLogsAsEvents === false) {
                return new Logger('Flare');
            }

            $handler = new FlareLogHandler(
                $app->make(Flare::class),
                $this->config->minimumReportLogLevel,
            );

            return (new Logger('Flare'))->pushHandler($handler);
        });

        Log::extend('flare', fn ($app) => $app['flare.logger']);
    }

    protected function registerShareButton(): void
    {
        config()->set('error-share.enabled', config('flare.enable_share_button'));
    }

    protected function configureTinker(): void
    {
        if ($this->app->runningInConsole()) {
            if (isset($_SERVER['argv']) && ['artisan', 'tinker'] === $_SERVER['argv']) {
                app(Flare::class)->sendReportsImmediately();
            }
        }
    }

    protected function configureOctane(): void
    {
        if (isset($_SERVER['LARAVEL_OCTANE'])) {
            $this->setupOctane();
        }
    }

    protected function registerViewExceptionMapper(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! method_exists($handler, 'map')) {
            return;
        }

        $handler->map(function (ViewException $viewException) {
            return $this->app->make(ViewExceptionMapper::class)->map($viewException);
        });
    }

    protected function configureQueue(): void
    {
        if (! $this->app->bound('queue')) {
            return;
        }

        $queue = $this->app->get('queue');

        // Reset before executing a queue job to make sure the job's log/query/dump recorders are empty.
        // When using a sync queue this also reports the queued reports from previous exceptions.
        $queue->before(function () {
            $this->resetFlare();

            // TODO: check in Horizon if this is working
            //app(Flare::class)->sendReportsImmediately();
        });

        // Send queued reports (and reset) after executing a queue job.
        $queue->after(function () {
            $this->resetFlare();
        });

        // TODO: performance tracing sampling kinda can be reset here?

        // Note: the $queue->looping() event can't be used because it's not triggered on Vapor
    }

    protected function prependTracingMiddleware(): void
    {
        $kernel = $this->app->make(HttpKernelInterface::class);

        if ($kernel instanceof HttpKernel) {
            $kernel->prependMiddleware(FlareTracingMiddleware::class);
        }
    }

    protected function setupOctane(): void
    {
        // TODO: performance tracing sampling kinda can be reset here?


        $this->app['events']->listen(RequestReceived::class, function () {
            $this->resetFlare();
        });

        $this->app['events']->listen(TaskReceived::class, function () {
            $this->resetFlare();
        });

        $this->app['events']->listen(TickReceived::class, function () {
            $this->resetFlare();
        });

        $this->app['events']->listen(RequestTerminated::class, function () {
            $this->resetFlare();
        });
    }

    protected function resetFlare(): void
    {
        $this->app->get(Flare::class)->reset();
    }
}

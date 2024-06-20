<?php

namespace Spatie\LaravelFlare;

use Exception;
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
use Monolog\Level;
use Monolog\Logger;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareProvider;
use Spatie\FlareClient\Performance\Resources\Resource;
use Spatie\FlareClient\Performance\Support\BackTracer as BaseBackTracer;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\LaravelFlare\Commands\TestCommand;
use Spatie\LaravelFlare\Exceptions\InvalidConfig;
use Spatie\LaravelFlare\Performance\Http\Middleware\FlareTracingMiddleware;
use Spatie\LaravelFlare\Performance\Support\BackTracer;
use Spatie\LaravelFlare\Performance\Support\Telemetry;
use Spatie\LaravelFlare\Recorders\DumpRecorder\DumpRecorder;
use Spatie\LaravelFlare\Support\FlareLogHandler;
use Spatie\LaravelFlare\Support\SentReports;
use Spatie\LaravelFlare\Views\ViewExceptionMapper;
use Spatie\LaravelFlare\Views\ViewFrameMapper;

class FlareServiceProvider extends ServiceProvider
{
    protected FlareProvider $provider;

    protected FlareConfig $config;

    public function register(): void
    {
        $this->registerConfig();

        $this->config = FlareConfig::fromLaravelConfig();

        $this->app->singleton(FlareConfig::class, fn () => $this->config);

        $this->provider = new FlareProvider($this->config, $this->app);

        $this->provider->register();

        $this->app->singleton(BaseBackTracer::class, fn () => $this->app->get(BackTracer::class));
        $this->app->singleton(Resource::class, fn () => Resource::build(
            $this->config->applicationName,
            $this->config->applicationVersion,
            Telemetry::NAME,
            Telemetry::VERSION
        )->host());

        $this->app->singleton(SentReports::class);
        $this->app->singleton(ViewFrameMapper::class);

        $this->registerLogHandler();
        $this->registerShareButton();

        if ($this->config->trace === false) {
            return;
        }

        $this->registerTracingMiddleware();
    }

    public function boot(): void
    {
        $this->provider->boot();

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->publishConfigs();
        }

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

    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/flare.php', 'flare');
    }

    protected function registerCommands(): void
    {
        $this->commands([
            TestCommand::class,
        ]);
    }

    protected function publishConfigs(): void
    {
        $this->publishes([
            __DIR__.'/../config/flare.php' => config_path('flare.php'),
        ], 'flare-config');
    }

    public function configureTinker(): void
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

    protected function registerLogHandler(): void
    {
        $this->app->singleton('flare.logger', function ($app) {
            $handler = new FlareLogHandler(
                $app->make(Flare::class),
                $app->make(SentReports::class),
            );

            $logLevelString = config('logging.channels.flare.level', 'error');

            $logLevel = $this->getLogLevel($logLevelString);

            $handler->setMinimumReportLogLevel($logLevel);

            return tap(
                new Logger('Flare'),
                fn (Logger $logger) => $logger->pushHandler($handler)
            );
        });

        Log::extend('flare', fn ($app) => $app['flare.logger']);
    }

    protected function registerShareButton()
    {
        config()->set('error-share.enabled', config('flare.enable_share_button'));
    }

    protected function registerTracingMiddleware(): void
    {
        $this->app->singleton(FlareTracingMiddleware::class);
    }

    protected function startRecorders(): void
    {
        foreach ($this->app->config['flare.recorders'] ?? [] as $recorder) {
            $this->app->make($recorder)->start();
        }
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
            app(Flare::class)->sendReportsImmediately();
        });

        // Send queued reports (and reset) after executing a queue job.
        $queue->after(function () {
            $this->resetFlare();
        });

        // Note: the $queue->looping() event can't be used because it's not triggered on Vapor
    }

    protected function prependTracingMiddleware(): void
    {
        $kernel = $this->app->make(HttpKernelInterface::class);

        if ($kernel instanceof HttpKernel) {
            $kernel->prependMiddleware(FlareTracingMiddleware::class);
        }
    }

    protected function getLogLevel(string $logLevelString): int
    {
        try {
            $logLevel = Level::fromName($logLevelString);
        } catch (Exception $exception) {
            $logLevel = null;
        }

        if (! $logLevel) {
            throw InvalidConfig::invalidLogLevel($logLevelString);
        }

        return $logLevel->value;
    }

    protected function getFlareMiddleware(): array
    {
        return collect(config('flare.flare_middleware'))
            ->map(function ($value, $key) {
                if (is_string($key)) {
                    $middlewareClass = $key;
                    $parameters = $value ?? [];
                } else {
                    $middlewareClass = $value;
                    $parameters = [];
                }

                return new $middlewareClass(...array_values($parameters));
            })
            ->values()
            ->toArray();
    }

    protected function setupOctane(): void
    {
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
        $this->app->get(SentReports::class)->clear();
        $this->app->get(Flare::class)->resetRecorders();
    }
}

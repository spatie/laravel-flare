<?php

namespace Spatie\LaravelFlare;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernelInterface;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\SyncQueue;
use Illuminate\Routing\Contracts\CallableDispatcher;
use Illuminate\Routing\Contracts\ControllerDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\ViewException;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TickReceived;
use Monolog\Logger;
use Spatie\FlareClient\Disabled\DisabledFlare;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareProvider;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Support\BackTracer as BaseBackTracer;
use Spatie\FlareClient\Support\GracefulSpanEnder;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelAttributesProvider;
use Spatie\LaravelFlare\Commands\TestCommand;
use Spatie\LaravelFlare\Http\Middleware\FlareTracingMiddleware;
use Spatie\LaravelFlare\Http\RouteDispatchers\CallableRouteDispatcher;
use Spatie\LaravelFlare\Http\RouteDispatchers\ControllerRouteDispatcher;
use Spatie\LaravelFlare\Support\BackTracer;
use Spatie\LaravelFlare\Support\FlareLogHandler;
use Spatie\LaravelFlare\Support\GracefulSpanEnder as LaravelGracefulSpanEnder;
use Spatie\LaravelFlare\Support\Telemetry;
use Spatie\LaravelFlare\Support\TracingKernel;
use Spatie\LaravelFlare\Views\ViewExceptionMapper;
use Spatie\LaravelFlare\Views\ViewFrameMapper;

class FlareServiceProvider extends ServiceProvider
{
    protected FlareProvider $provider;

    protected FlareConfig $config;

    protected ?Flare $flare = null;

    public function register(): void
    {
        if (! $this->app->has(FlareConfig::class)) {
            $this->mergeConfigFrom(__DIR__.'/../config/flare.php', 'flare');

            $this->config = FlareConfig::fromLaravelConfig();

            $this->app->singleton(FlareConfig::class, fn () => $this->config);
        } else {
            $this->config = $this->app->make(FlareConfig::class);
        }

        $this->registerShareButton();

        if (empty($this->config->apiToken)) {
            $this->app->singleton(Flare::class, fn () => new DisabledFlare());

            return;
        }

        $this->registerLogHandler();

        $this->provider = new FlareProvider(
            $this->config,
            $this->app,
            function (Container $container, string $class, array $config) {
                $this->app->singleton($class);
                $this->app->when($class)->needs('$config')->give($config);

                if (method_exists($class, 'registered')) {
                    $class::registered($container, $config);
                }
            }
        );

        $this->provider->register();

        $this->app->singleton(GracefulSpanEnder::class, LaravelGracefulSpanEnder::class);

        $this->app->singleton(BaseBackTracer::class, fn () => new BackTracer(
            $this->app->make(ViewFrameMapper::class),
            $this->config->applicationPath
        ));

        $this->app->singleton(ViewFrameMapper::class);

        if ($this->config->trace === false) {
            return;
        }

        $this->app->extend(
            Resource::class,
            fn (Resource $resource) => $resource
                ->telemetrySdkName(Telemetry::NAME)
                ->telemetrySdkVersion(Telemetry::VERSION)
                ->addAttributes((new LaravelAttributesProvider())->toArray())
        );

        $this->app->extend(
            Scope::class,
            fn (Scope $scope) => $scope
                ->name(Telemetry::NAME)
                ->version(Telemetry::VERSION)
        );

        $this->app->singleton(FlareTracingMiddleware::class);

        TracingKernel::registerCallbacks($this->app);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/flare.php.stub' => config_path('flare.php'),
            ], 'flare-config');
        }

        if (empty($this->config->apiToken)) {
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

        $this->extendRouteDispatchers();
        $this->prependTracingMiddleware();

        TracingKernel::bootCallbacks($this->app);
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
                $this->config->logStackTraces,
            );

            return (new Logger('Flare'))->pushHandler($handler);
        });

        Log::extend('flare', fn ($app) => $app['flare.logger']);
    }

    protected function registerShareButton(): void
    {
        config()->set('error-share.enabled', $this->config->enableShareButton);
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
        if (app()->bound('octane')) {
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
        // When using a sync queue this also reports the queued reports from previous exceptions but
        // does not clear the custom context as that would remove any context set in the current request.
        $queue->before(function (JobProcessing $event) use ($queue) {
            $isSyncQueue = $queue->connection($event->connectionName) instanceof SyncQueue;

            $this->getFlare()->reset(reports: true, traces: false, clearCustomContext: ! $isSyncQueue);
        });

        // Send queued reports (and reset) after executing a queue job.
        $queue->after(function (JobProcessed $event) use ($queue) {
            $isSyncQueue = $queue->connection($event->connectionName) instanceof SyncQueue;

            $this->getFlare()->reset(reports: true, traces: false, clearCustomContext: ! $isSyncQueue);
        });

        // Note: the $queue->looping() event can't be used because it's not triggered on Vapor
    }

    protected function extendRouteDispatchers(): void
    {
        $this->app->extend(
            CallableDispatcher::class,
            fn (CallableDispatcher $dispatcher) => new CallableRouteDispatcher($this->app->make(Tracer::class), $dispatcher)
        );

        $this->app->extend(
            ControllerDispatcher::class,
            fn (ControllerDispatcher $dispatcher) => new ControllerRouteDispatcher($this->app->make(Tracer::class), $dispatcher)
        );
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
        $this->app['events']->listen(RequestReceived::class, function () {
            $this->getFlare()->reset(reports: true, traces: false);
        });

        $this->app['events']->listen(TaskReceived::class, function () {
            $this->getFlare()->reset(reports: true, traces: false);
        });

        $this->app['events']->listen(TickReceived::class, function () {
            $this->getFlare()->reset(reports: true, traces: false);
        });

        $this->app['events']->listen(RequestTerminated::class, function () {
            TracingKernel::appTerminated(
                $this->app,
                $this->app->make(Flare::class)
            );

            $this->getFlare()->reset();
        });
    }

    protected function getFlare(): Flare
    {
        return $this->flare ??= $this->app->make(Flare::class);
    }
}

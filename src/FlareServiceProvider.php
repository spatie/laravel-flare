<?php

namespace Spatie\LaravelFlare;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernelInterface;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
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
use Spatie\FlareClient\Enums\FlareMode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareProvider;
use Spatie\FlareClient\Logger as FlareLogger;
use Spatie\FlareClient\Recorders\ContextRecorder\ContextRecorder as BaseContextRecorder;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer as BaseBackTracer;
use Spatie\FlareClient\Support\GracefulSpanEnder;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Time\Time;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelAttributesProvider;
use Spatie\LaravelFlare\Commands\TestCommand;
use Spatie\LaravelFlare\Enums\LaravelCollectType;
use Spatie\LaravelFlare\Enums\SpanType as LaravelSpanType;
use Spatie\LaravelFlare\Http\Middleware\FlareTracingMiddleware;
use Spatie\LaravelFlare\Http\RouteDispatchers\CallableRouteDispatcher;
use Spatie\LaravelFlare\Http\RouteDispatchers\ControllerRouteDispatcher;
use Spatie\LaravelFlare\Recorders\ContextRecorder\ContextRecorder;
use Spatie\LaravelFlare\Support\BackTracer;
use Spatie\LaravelFlare\Support\CollectsResolver;
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

    protected int $registeredTimeUnixNano;

    public function register(): void
    {
        if (! $this->app->has(FlareConfig::class)) {
            $this->mergeConfigFrom(__DIR__.'/../config/flare.php', 'flare');

            $this->config = FlareConfig::fromLaravelConfig();

            $this->app->singleton(FlareConfig::class, fn () => $this->config);
        } else {
            $this->config = $this->app->make(FlareConfig::class);
        }

        $this->provider = new FlareProvider(
            config: $this->config,
            container: $this->app,
            collectsResolver: CollectsResolver::class,
            registerRecorderAndMiddlewaresCallback: function (Container $container, string $class, array $config) {
                $this->app->singleton($class);
                $this->app->when($class)->needs('$config')->give($config);

                if (method_exists($class, 'registered')) {
                    $class::registered($container, $config);
                }
            },
            isUsingSubtasksClosure: fn () => $this->app->runningConsoleCommand(['horizon:work', 'queue:work', 'serve', 'vapor:work', 'octane:start', 'octane:reload'])
                || (bool) env('LARAVEL_OCTANE', false) !== false,
            gracefulSpanEnderClosure: function (Span $span) {
                /** @var SpanType|LaravelSpanType|string|null $type */
                $type = $span->attributes['flare.span_type'] ?? null;

                if ($type === null) {
                    return true;
                }

                // Application, request will always be handled by Laravel and thus us, terminating by lifecycle shutdown
                $shouldNotEnd = $type === SpanType::Application || $type === SpanType::Request || $type === SpanType::ApplicationTerminating;

                return $shouldNotEnd === false;
            }
        );

        $this->registerShareButton();
        $this->registerLogHandler();

        $this->provider->register();

        $this->app->singleton(BaseBackTracer::class, fn () => new BackTracer(
            $this->app->make(ViewFrameMapper::class),
            $this->config->applicationPath
        ));

        $this->app->singleton(ViewFrameMapper::class);

        $this->app->registered(fn () => $this->registeredTimeUnixNano = $this->app->get(Time::class)->getCurrentTime());

        if ($this->config->trace === false) {
            return;
        }

        $this->app->extend(
            Resource::class,
            fn (Resource $resource) => $resource
                ->telemetrySdkName(Telemetry::getName())
                ->telemetrySdkVersion(Telemetry::getVersion())
                ->addAttributes((new LaravelAttributesProvider())->toArray())
        );

        $this->app->extend(
            Scope::class,
            fn (Scope $scope) => $scope
                ->name(Telemetry::getName())
                ->version(Telemetry::getVersion())
        );

        $this->app->singleton(FlareTracingMiddleware::class);
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

        $this->provider->boot();

        $this->configureTinker();
        $this->configureOctane();
        $this->registerViewExceptionMapper();

        if ($this->provider->mode !== FlareMode::Disabled) {
            $lifecycle = $this->app->get(Lifecycle::class);

            $startTimeUnixNano = TimeHelper::phpMicroTime(
                defined('LARAVEL_START') ? LARAVEL_START : $_SERVER['REQUEST_TIME_FLOAT']
            );

            $lifecycle->start(
                timeUnixNano: $startTimeUnixNano,
                traceparent: request()->hasHeader('traceparent') ? request()->header('traceparent') : null,
            );

            if (! isset($this->registeredTimeUnixNano)) {
                $lifecycle->trash();

                return;
            }

            $lifecycle->register(
                timeUnixNano: $startTimeUnixNano
            );

            $lifecycle->registered(
                timeUnixNano: $this->registeredTimeUnixNano,
            );

            $lifecycle->boot(
                timeUnixNano: $this->registeredTimeUnixNano
            );

            $this->app->booted(fn () => $lifecycle->booted());
            $this->app->terminating(function () use ($lifecycle) {
                $lifecycle->terminating();

                $this->app->terminating(fn () => $lifecycle->terminated());
            });
        }

        if ($this->config->trace === false) {
            return;
        }

        $this->extendRouteDispatchers();
        $this->prependTracingMiddleware();
    }

    protected function registerLogHandler(): void
    {
        $mode = $this->provider->mode;

        Log::extend('flare', function ($app, $config) use ($mode)  {
            if ($mode === FlareMode::Disabled) {
                return new Logger('Flare');
            }

            $handler = new FlareLogHandler(
                $app->make(FlareLogger::class),
                $this->config->minimalLogLevel ?? FlareLogHandler::DEFAULT_MONOLOG_LEVEL,
                $config['bubble'] ?? true,
            );

            return (new Logger('Flare'))->pushHandler($handler);
        });
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
        if (app()->bound('octane') === false) {
            return;
        }


        if ($this->provider->mode === FlareMode::Disabled) {
            return;
        }

        app('events')->listen(RequestReceived::class, function () {
            $this->getFlare()->lifecycle->startSubtask();
        });

        app('events')->listen(RequestTerminated::class, function () {
            $this->getFlare()->lifecycle->endSubtask();
        });

        app('events')->listen(TaskReceived::class, function () {
            $this->getFlare()->lifecycle->startSubtask();
        });

        app('events')->listen(TickReceived::class, function () {
            $this->getFlare()->lifecycle->endSubtask();
        });
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

    protected function getFlare(): Flare
    {
        return $this->flare ??= $this->app->make(Flare::class);
    }
}

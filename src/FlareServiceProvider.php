<?php

namespace Spatie\LaravelFlare;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernelInterface;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
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
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\FlareMode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareProvider;
use Spatie\FlareClient\Logger as FlareLogger;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer as BaseBackTracer;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Time\Time;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelAttributesProvider;
use Spatie\LaravelFlare\Commands\TestCommand;
use Spatie\LaravelFlare\Enums\SpanType as LaravelSpanType;
use Spatie\LaravelFlare\FlareMiddleware\AddJobInformation;
use Spatie\LaravelFlare\Http\Middleware\FlareTracingMiddleware;
use Spatie\LaravelFlare\Http\RouteDispatchers\CallableRouteDispatcher;
use Spatie\LaravelFlare\Http\RouteDispatchers\ControllerRouteDispatcher;
use Spatie\LaravelFlare\Support\BackTracer;
use Spatie\LaravelFlare\Support\CollectsResolver;
use Spatie\LaravelFlare\Support\FlareLogHandler;
use Spatie\LaravelFlare\Support\Telemetry;
use Spatie\LaravelFlare\Views\ViewExceptionMapper;
use Spatie\LaravelFlare\Views\ViewFrameMapper;

class FlareServiceProvider extends ServiceProvider
{
    protected FlareProvider $provider;

    protected FlareConfig $config;

    protected ?Flare $flare = null;

    protected int $registeredTimeUnixNano;

    /**
     * @param Closure(\Spatie\FlareClient\Support\Container|IlluminateContainer, class-string<Recorder>, array):void|null $registerRecorderAndMiddlewaresCallback
     * @param class-string<\Spatie\FlareClient\Support\CollectsResolver>|null $collectsResolver
     * @param Closure():bool|null $isUsingSubtasksClosure
     * @param Closure():void|null $subtaskEndedClosure
     * @param Closure(bool):bool|null $shouldMakeSamplingDecisionClosure
     * @param Closure(Span):bool|null $gracefulSpanEnderClosure
     */
    public function __construct(
        Application $app,
        protected ?string $collectsResolver = null,
        protected ?Closure $registerRecorderAndMiddlewaresCallback = null,
        protected ?Closure $isUsingSubtasksClosure = null,
        protected ?Closure $subtaskEndedClosure = null,
        protected ?Closure $shouldMakeSamplingDecisionClosure = null,
        protected ?Closure $gracefulSpanEnderClosure = null,
        protected bool $disableApiQueue = false
    ) {
        parent::__construct($app);

        $this->collectsResolver ??= CollectsResolver::class;
        $this->registerRecorderAndMiddlewaresCallback ??= function (Container $container, string $class, array $config) {
            $this->app->singleton($class);
            $this->app->when($class)->needs('$config')->give($config);

            if (method_exists($class, 'registered')) {
                $class::registered($container, $config);
            }
        };
        $this->isUsingSubtasksClosure ??= fn () => $this->app->runningConsoleCommand(
            ['horizon:work', 'queue:work', 'serve', 'vapor:work', 'octane:start', 'octane:reload']
        ) || (bool) env('LARAVEL_OCTANE', false) !== false;
        $this->subtaskEndedClosure ??= function () {
            AddJobInformation::clearLatestJobInfo();
        };
        $this->gracefulSpanEnderClosure ??= function (Span $span) {
            /** @var SpanType|LaravelSpanType|string|null $type */
            $type = $span->attributes['flare.span_type'] ?? null;

            if ($type === null) {
                return true;
            }

            // Application, request will always be handled by Laravel and thus us, terminating by lifecycle shutdown
            $shouldNotEnd = $type === SpanType::Application || $type === SpanType::Request || $type === SpanType::ApplicationTerminating;

            return $shouldNotEnd === false;
        };
        $this->disableApiQueue ??= $this->app->runningInConsole() && isset($_SERVER['argv']) && in_array('tinker', $_SERVER['argv']);
    }

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
            registerRecorderAndMiddlewaresCallback: $this->registerRecorderAndMiddlewaresCallback,
            isUsingSubtasksClosure: $this->isUsingSubtasksClosure,
            subtaskEndedClosure: $this->subtaskEndedClosure,
            gracefulSpanEnderClosure: $this->gracefulSpanEnderClosure,
            disableApiQueue: $this->disableApiQueue,
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

        Log::extend('flare', function ($app, $config) use ($mode) {
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

        // TODO: check
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

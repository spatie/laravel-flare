<?php

namespace Spatie\LaravelFlare;

use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernelInterface;
use Illuminate\Foundation\Application;
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
use Spatie\ErrorSolutions\SolutionProviderRepository;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareMiddleware\AddSolutions;
use Spatie\FlareClient\Http\Client;
use Spatie\FlareClient\Performance\Exporters\JsonExporter;
use Spatie\FlareClient\Performance\Resources\Resource;
use Spatie\FlareClient\Performance\Sampling\Sampler;
use Spatie\FlareClient\Performance\Scopes\Scope;
use Spatie\FlareClient\Performance\Support\BackTracer as BaseBackTracer;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\FlareClient\Senders\Sender;
use Spatie\ErrorSolutions\Contracts\SolutionProviderRepository as SolutionProviderRepositoryContract;
use Spatie\LaravelFlare\Commands\TestCommand;
use Spatie\LaravelFlare\ContextProviders\LaravelContextProviderDetector;
use Spatie\LaravelFlare\Exceptions\InvalidConfig;
use Spatie\LaravelFlare\FlareMiddleware\AddJobs;
use Spatie\LaravelFlare\FlareMiddleware\AddLogs;
use Spatie\LaravelFlare\FlareMiddleware\AddQueries;
use Spatie\LaravelFlare\Performance\Http\Middleware\FlareTracingMiddleware;
use Spatie\LaravelFlare\Performance\Support\BackTracer;
use Spatie\LaravelFlare\Recorders\DumpRecorder\DumpRecorder;
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogRecorder;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder;
use Spatie\LaravelFlare\Support\FlareLogHandler;
use Spatie\LaravelFlare\Support\SentReports;
use Spatie\LaravelFlare\Views\ViewExceptionMapper;
use Spatie\LaravelFlare\Views\ViewFrameMapper;
use function Symfony\Component\Translation\t;

class FlareServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
        $this->registerSampler();
        $this->registerSender();
        $this->registerBackTracer();
        $this->registerFlare();
        $this->registerSolutions();
        $this->registerRecorders();
        $this->registerLogHandler();
        $this->registerShareButton();



        if (config('flare.performance.enabled') === false) {
            return;
        }

        $this->registerTracingMiddleware();

        register_shutdown_function(function () {
            $this->app->make(Tracer::class)->transmit();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->publishConfigs();
        }

        $this->configureTinker();
        $this->configureOctane();
        $this->registerViewExceptionMapper();
        $this->startRecorders();
        $this->configureQueue();

        if (config('flare.performance.enabled') === false) {
            return;
        }

        $this->prependTracingMiddleware();
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

    protected function registerFlare(): void
    {
        $this->app->singleton(Client::class, function (){
            return new Client(
                apiToken: config('flare.key'),
                baseUrl: config('flare.base_url', 'https://flareapp.io/api'),
                timeout: config('flare.timeout', 10),
                sender: $this->app->make(Sender::class)
            );
        });

        $this->app->singleton(Tracer::class, function (){
            return new Tracer(
                client: app()->make(Client::class),
                exporter: new JsonExporter(),
                backTracer: app()->make(BackTracer::class),
                resource: Resource::build(
                    serviceName: config('app.name'),
                    serviceVersion: config('app.version'),
                ),
                scope: Scope::build()
            );
        });


        $this->app->singleton(Flare::class, function () {
            $flare = new Flare(
                app()->make(Client::class),
                app()->make(Tracer::class),
                new LaravelContextProviderDetector()
            );

            return $flare
                ->applicationPath(base_path())
                ->setStage(app()->environment())
                ->registerMiddleware($this->getFlareMiddleware())
                ->registerMiddleware(new AddSolutions(new SolutionProviderRepository($this->getSolutionProviders())))
                ->argumentReducers(config('flare.argument_reducers', []))
                ->withStackFrameArguments(
                    config('flare.with_stack_frame_arguments', true),
                    config('flare.force_stack_frame_arguments_ini_setting', true)
                );
        });

        $this->app->singleton(SentReports::class);
        $this->app->singleton(ViewFrameMapper::class);
    }

    protected function registerSolutions(): void
    {
        $solutionProviders = $this->getSolutionProviders();
        $solutionProviderRepository = new SolutionProviderRepository($solutionProviders);

        $this->app->singleton(SolutionProviderRepositoryContract::class, fn () => $solutionProviderRepository);
    }

    protected function registerRecorders(): void
    {
        $this->app->singleton(DumpRecorder::class);

        $this->app->singleton(LogRecorder::class, function (Application $app): LogRecorder {
            return new LogRecorder(
                $app,
                $app->make(Tracer::class),
                config()->get('flare.flare_middleware.'.AddLogs::class.'.maximum_number_of_collected_logs'),
                config()->get('flare.flare_middleware.'.AddLogs::class.'.trace_logs')
            );
        });

        $this->app->singleton(
            QueryRecorder::class,
            function (Application $app): QueryRecorder {
                return new QueryRecorder(
                    $app,
                    $app->make(Tracer::class),
                    config('flare.flare_middleware.'.AddQueries::class.'.report_query_bindings', true),
                    config('flare.flare_middleware.'.AddQueries::class.'.maximum_number_of_collected_queries', 200),
                    config('flare.flare_middleware.'.AddQueries::class.'.trace_query_origin_threshold', 200)
                );
            }
        );

        $this->app->singleton(JobRecorder::class, function (Application $app): JobRecorder {
            return new JobRecorder(
                $app,
                config('flare.flare_middleware.'.AddJobs::class.'.max_chained_job_reporting_depth', 5)
            );
        });
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

    protected function registerSender(): void
    {
        $this->app->singleton(Sender::class, function (Application $app) {
            $config = $app->make('config');

            return $this->app->make(
                $config->get('flare.sender'),
            );
        });
    }

    protected function registerSampler(): void
    {
        $this->app->singleton(Sampler::class, function (Application $app) {
            $config = $app->make('config');

            return $this->app->make(
                $config->get('flare.performance.sampling.class'),
                $config->get('flare.performance.sampling')
            );
        });
    }

    protected function registerBackTracer(): void
    {
        $this->app->singleton(BaseBackTracer::class, fn (Application $app) => $app->make(BackTracer::class));
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

    protected function getSolutionProviders(): array
    {
        return collect(config('flare.solution_providers'))
            ->reject(
                fn (string $class) => in_array($class, config('flare.ignored_solution_providers'))
            )
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

        if (config('flare.flare_middleware.'.AddLogs::class)) {
            $this->app->make(LogRecorder::class)->reset();
        }

        if (config('flare.flare_middleware.'.AddQueries::class)) {
            $this->app->make(QueryRecorder::class)->reset();
        }

        if (config('flare.flare_middleware.'.AddJobs::class)) {
            $this->app->make(JobRecorder::class)->reset();
        }

        $this->app->make(DumpRecorder::class)->reset();
    }
}

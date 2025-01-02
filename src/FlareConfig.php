<?php

namespace Spatie\LaravelFlare;

use Monolog\Level;
use Spatie\Backtrace\Arguments\ArgumentReducers as BackTraceArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\ArrayArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\BaseTypeArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\ClosureArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\DateTimeArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\DateTimeZoneArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\EnumArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\StdClassArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\StringableArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\SymphonyRequestArgumentReducer;
use Spatie\ErrorSolutions\SolutionProviders\BadMethodCallSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\DefaultDbNameSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\GenericLaravelExceptionSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\IncorrectValetDbCredentialsSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\InvalidRouteActionSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\MissingAppKeySolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\MissingColumnSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\MissingImportSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\MissingLivewireComponentSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\MissingMixManifestSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\MissingViteManifestSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\OpenAiSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\RunningLaravelDuskInProductionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\SailNetworkSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\TableNotFoundSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\UndefinedViewVariableSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\UnknownMariadbCollationSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\UnknownMysql8CollationSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\UnknownValidationSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\Laravel\ViewNotFoundSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\MergeConflictSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\UndefinedPropertySolutionProvider;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\FlareConfig as BaseFlareConfig;
use Spatie\FlareClient\FlareMiddleware\AddGitInformation;
use Spatie\FlareClient\FlareMiddleware\AddSolutions;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Support\TraceLimits;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\LaravelFlare\ArgumentReducers\ArgumentReducers;
use Spatie\LaravelFlare\ArgumentReducers\CollectionArgumentReducer;
use Spatie\LaravelFlare\ArgumentReducers\ModelArgumentReducer;
use Spatie\LaravelFlare\FlareMiddleware\AddConsoleInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionHandledStatus;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddJobInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddLaravelContext;
use Spatie\LaravelFlare\FlareMiddleware\AddLaravelInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddRequestInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddViewInformation as AddViewInformation;
use Spatie\LaravelFlare\Recorders\CacheRecorder\CacheRecorder;
use Spatie\LaravelFlare\Recorders\CommandRecorder\CommandRecorder;
use Spatie\LaravelFlare\Recorders\FilesystemRecorder\FilesystemRecorder;
use Spatie\LaravelFlare\Recorders\HttpRecorder\ExternalHttpRecorder;
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogRecorder;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder;
use Spatie\LaravelFlare\Recorders\QueueRecorder\QueueRecorder;
use Spatie\LaravelFlare\Recorders\RoutingRecorder\RoutingRecorder;
use Spatie\LaravelFlare\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\LaravelFlare\Recorders\ViewRecorder\ViewRecorder;
use Spatie\LaravelFlare\Support\FlareLogHandler;

class FlareConfig extends BaseFlareConfig
{
    public bool $sendLogsAsEvents = true;

    public Level $minimumReportLogLevel = Level::Error;

    public bool $enableShareButton = true;

    public static function fromLaravelConfig(): self
    {
        $argumentReducers = config()->has('flare.argument_reducers') ? ArgumentReducers::create(
            config('flare.argument_reducers')
        ) : ArgumentReducers::default();

        $config = new self(
            apiToken: config('flare.key'),
            baseUrl: config('flare.base_url', 'https://flareapp.io/api'),
            middleware: config('flare.middleware'),
            recorders: config('flare.recorders'),
            reportErrorLevels: config('flare.report_error_levels'),
            applicationPath: base_path(),
            applicationName: config('app.name'),
            applicationStage: app()->environment(),
            argumentReducers: $argumentReducers,
            withStackFrameArguments: config('flare.with_stack_frame_arguments'),
            forcePHPStackFrameArgumentsIniSetting: config('flare.force_stack_frame_arguments_ini_setting'),
            sender: config('flare.sender.class'),
            senderConfig: config('flare.sender.config', []),
            solutionsProviders: config('flare.solution_providers'),
            trace: config('flare.tracing.enabled'),
            sampler: config('flare.tracing.sampler.class'),
            samplerConfig: config('flare.tracing.sampler.config'),
            traceLimits: new TraceLimits(
                maxSpans: config('flare.tracing.limits.max_spans'),
                maxAttributesPerSpan: config('flare.tracing.limits.max_attributes_per_span'),
                maxSpanEventsPerSpan: config('flare.tracing.limits.max_span_events_per_span'),
                maxAttributesPerSpanEvent: config('flare.tracing.limits.max_attributes_per_span_event')
            ),
            traceThrowables: config('flare.tracing.trace_throwables'),
            censorClientIps: config('flare.censor.client_ips'),
            censorHeaders: config('flare.censor.headers'),
            censorBodyFields: config('flare.censor.body_fields'),
            userAttributesProvider: config('flare.attribute_providers.user'),
            overriddenGroupings: config('flare.overridden_groupings'),
        );

        $config->sendLogsAsEvents = config('flare.send_logs_as_events', true);
        $config->minimumReportLogLevel = config()->has('logging.channels.flare.level')
            ? FlareLogHandler::logLevelFromName(config('logging.channels.flare.level'))
            : Level::Error;
        $config->enableShareButton = config('flare.enable_share_button', true);

        return $config;
    }

    public static function defaultMiddleware(): array
    {
        return [
            AddViewInformation::class => [],
            AddConsoleInformation::class => [],
            AddRequestInformation::class => [],
            AddJobInformation::class => [],
            AddGitInformation::class => [],
            AddLaravelInformation::class => [],
            AddExceptionInformation::class => [],
            AddLaravelContext::class => [],
            AddExceptionHandledStatus::class => [],
            AddSolutions::class => [],
        ];
    }

    public static function defaultRecorders(): array
    {
        return [
            RoutingRecorder::class => [],
            CommandRecorder::class => [
                'trace' => true,
                'report' => true,
                'max_reported' => 10,
            ],
            CacheRecorder::class => [
                'trace' => true,
                'report' => true,
                'max_reported' => 100,
                'operations' => [CacheOperation::Get, CacheOperation::Set, CacheOperation::Forget],
            ],
            DumpRecorder::class => [
                'trace' => false,
                'report' => true,
                'max_reported' => 25,
                'find_dump_origin' => true,
            ],
            GlowRecorder::class => [
                'trace' => true,
                'report' => true,
                'max_reported' => 100,
            ],
            QueueRecorder::class => [
                'trace' => true,
                'report' => true,
                'max_reported' => 100,
            ],
            JobRecorder::class => [
                'trace' => true,
                'report' => true,
                'max_reported' => 100,
                'max_chained_job_reporting_depth' => 2,
            ],
            FilesystemRecorder::class => [
                'trace' => true,
                'report' => true,
                'max_reported' => 100,
                'track_all_disks' => true,
            ],
            ExternalHttpRecorder::class => [
                'trace' => true,
                'report' => true,
                'max_reported' => 100,
            ],
            LogRecorder::class => [
                'trace' => true,
                'report' => true,
                'max_reported' => 100,
            ],
            QueryRecorder::class => [
                'trace' => true,
                'report' => true,
                'max_reported' => 100,
                'include_bindings' => true,
                'find_origin' => true,
                'find_origin_threshold' => TimeHelper::milliseconds(700),
            ],
            TransactionRecorder::class => [
                'trace' => true,
                'report' => true,
                'max_reported' => 100,
            ],
//        RedisCommandRecorder::class => [
//            'trace' => true,
//            'report' => true,
//            'max_reported' => 100,
//        ],
            ViewRecorder::class => [
                'trace' => true,
            ],
        ];
    }

    public static function defaultSolutionProviders(): array
    {
        return [
            // from spatie/ignition
            BadMethodCallSolutionProvider::class,
            MergeConflictSolutionProvider::class,
            UndefinedPropertySolutionProvider::class,

            // from spatie/laravel-flare
            IncorrectValetDbCredentialsSolutionProvider::class,
            MissingAppKeySolutionProvider::class,
            DefaultDbNameSolutionProvider::class,
            TableNotFoundSolutionProvider::class,
            MissingImportSolutionProvider::class,
            InvalidRouteActionSolutionProvider::class,
            ViewNotFoundSolutionProvider::class,
            RunningLaravelDuskInProductionProvider::class,
            MissingColumnSolutionProvider::class,
            UnknownValidationSolutionProvider::class,
            MissingMixManifestSolutionProvider::class,
            MissingViteManifestSolutionProvider::class,
            MissingLivewireComponentSolutionProvider::class,
            UndefinedViewVariableSolutionProvider::class,
            GenericLaravelExceptionSolutionProvider::class,
            OpenAiSolutionProvider::class,
            SailNetworkSolutionProvider::class,
            UnknownMysql8CollationSolutionProvider::class,
            UnknownMariadbCollationSolutionProvider::class,
        ];
    }

    public static function defaultArgumentReducers(): array
    {
        return [
            BaseTypeArgumentReducer::class,
            ArrayArgumentReducer::class,
            StdClassArgumentReducer::class,
            EnumArgumentReducer::class,
            ClosureArgumentReducer::class,
            DateTimeArgumentReducer::class,
            DateTimeZoneArgumentReducer::class,
            SymphonyRequestArgumentReducer::class,
            ModelArgumentReducer::class,
            CollectionArgumentReducer::class,
            StringableArgumentReducer::class,
        ];
    }

    public function useDefaults(): static
    {
        return parent::useDefaults()
            ->sendLogsAsEvents()
            ->addLivewireComponents()
            ->addLaravelInfo()
            ->addLaravelContext()
            ->addExceptionInfo()
            ->addJobInfo()
            ->addJobs()
            ->addExceptionHandledStatus()
            ->addCacheEvents()
            ->addLogs()
            ->addQueries()
            ->addCommands()
            ->addTransactions();
    }

    public function sendLogsAsEvents(
        bool $sendLogsAsEvents = true,
        Level $minimumReportLogLevel = Level::Error
    ): static {
        $this->sendLogsAsEvents = $sendLogsAsEvents;
        $this->minimumReportLogLevel = $minimumReportLogLevel;

        return $this;
    }

    public function addRequestInfo(
        string $middleware = AddRequestInformation::class,
    ): static {
        parent::addRequestInfo(
            middleware: $middleware,
        );

        return $this;
    }

    /**
     * @param class-string<FlareMiddleware> $middleware
     */
    public function addLivewireComponents(
        bool $includeLivewireComponents = true,
        string $middleware = AddRequestInformation::class
    ): static {
        if (! array_key_exists($middleware, $this->middleware)) {
            $this->middleware[$middleware] = [];
        }

        $this->middleware[$middleware] += [
            'include_livewire_components' => $includeLivewireComponents,
        ];

        return $this;
    }

    /**
     * @param class-string<FlareMiddleware> $middleware
     */
    public function addConsoleInfo(string $middleware = AddConsoleInformation::class): static
    {
        parent::addConsoleInfo(middleware: $middleware);

        return $this;
    }

    /**
     * @param class-string<FlareMiddleware> $middleware
     */
    public function addLaravelInfo(
        string $middleware = AddLaravelInformation::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    /**
     * @param class-string<FlareMiddleware> $middleware
     */
    public function addLaravelContext(
        string $middleware = AddLaravelContext::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    /**
     * @param class-string<FlareMiddleware> $middleware
     */
    public function addExceptionInfo(
        string $middleware = AddExceptionInformation::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    /**
     * @param class-string<FlareMiddleware> $middleware
     */
    public function addJobInfo(
        string $middleware = AddJobInformation::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    /**
     * @param class-string<Recorder> $recorder
     */
    public function addJobs(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 10,
        int $maxChainedJobReportingDepth = 2,
        string $recorder = JobRecorder::class
    ): static {
        $this->recorders[$recorder] = [
            'trace' => $trace,
            'report' => $report,
            'max_reported' => $maxReported,
            'max_chained_job_reporting_depth' => $maxChainedJobReportingDepth,
        ];

        return $this;
    }

    /**
     * @param class-string<FlareMiddleware> $middleware
     */
    public function addExceptionHandledStatus(
        string $middleware = AddExceptionHandledStatus::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    /**
     * @param class-string<Recorder> $recorder
     */
    public function addCacheEvents(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 100,
        array $operations = [CacheOperation::Get, CacheOperation::Set, CacheOperation::Forget],
        string $recorder = CacheRecorder::class,
    ): static {
        parent::addCacheEvents($trace, $report, $maxReported, $operations, $recorder);

        return $this;
    }

    /**
     * @param class-string<Recorder> $recorder
     */
    public function addLogs(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 10,
        string $recorder = LogRecorder::class
    ): static {
        parent::addLogs($trace, $report, $maxReported, $recorder);

        return $this;
    }

    /**
     * @param class-string<Recorder> $recorder
     */
    public function addQueries(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 100,
        bool $includeBindings = true,
        bool $findOrigin = true,
        ?int $findOriginThreshold = 300_000,
        string $recorder = QueryRecorder::class,
    ): static {
        parent::addQueries($trace, $report, $maxReported, $includeBindings, $findOrigin, $findOriginThreshold, $recorder);

        return $this;
    }

    /**
     * @param class-string<Recorder> $recorder
     */
    public function addTransactions(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 100,
        string $recorder = TransactionRecorder::class
    ): static {
        parent::addTransactions($trace, $report, $maxReported, $recorder);

        return $this;
    }

    public function addStackFrameArguments(
        bool $withStackFrameArguments = true,
        BackTraceArgumentReducers|array|string|ArgumentReducer|null $argumentReducers = null,
        bool $forcePHPIniSetting = true
    ): static {
        if ($argumentReducers === null) {
            $argumentReducers = ArgumentReducers::default();
        }

        return parent::addStackFrameArguments(
            $withStackFrameArguments,
            $argumentReducers,
            $forcePHPIniSetting
        );
    }

    /**
     * @param class-string<Recorder> $recorder
     */
    public function addCommands(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 10,
        string $recorder = CommandRecorder::class
    ): static {
        parent::addCommands($trace, $report, $maxReported, $recorder);

        return $this;
    }
}

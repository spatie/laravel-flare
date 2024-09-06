<?php

namespace Spatie\LaravelFlare;

use Monolog\Level;
use Spatie\Backtrace\Arguments\ArgumentReducers as BackTraceArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\FlareConfig as BaseFlareConfig;
use Spatie\FlareClient\Support\TraceLimits;
use Spatie\LaravelFlare\ArgumentReducers\ArgumentReducers;
use Spatie\LaravelFlare\FlareMiddleware\AddConsoleInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionHandledStatus;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddLaravelContext;
use Spatie\LaravelFlare\FlareMiddleware\AddLaravelInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddRequestInformation;
use Spatie\LaravelFlare\Recorders\CacheRecorder\CacheRecorder;
use Spatie\LaravelFlare\Recorders\CommandRecorder\CommandRecorder;
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogRecorder;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder;
use Spatie\LaravelFlare\Recorders\TransactionRecorder\TransactionRecorder;
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
            timeout: config('flare.timeout', 10),
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
        );

        $config->sendLogsAsEvents = config('flare.send_logs_as_events', true);
        $config->minimumReportLogLevel = config()->has('logging.channels.flare.level')
            ? FlareLogHandler::logLevelFromName(config('logging.channels.flare.level'))
            : Level::Error;
        $config->enableShareButton = config('flare.enable_share_button', true);

        return $config;
    }

    // TODO: make sure this is up to date with the flare.php config file

    public function useDefaults(): static
    {
        return parent::useDefaults()
            ->sendLogsAsEvents()
            ->addLivewireComponents()
            ->addLaravelInfo()
            ->addLaravelContext()
            ->addExceptionInfo()
            ->addJobInfo()
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

    public function addConsoleInfo(string $middleware = AddConsoleInformation::class): static
    {
        parent::addConsoleInfo(middleware: $middleware);

        return $this;
    }

    public function addLaravelInfo(
        string $middleware = AddLaravelInformation::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    public function addLaravelContext(
        string $middleware = AddLaravelContext::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    public function addExceptionInfo(
        string $middleware = AddExceptionInformation::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    public function addJobInfo(
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

    public function addExceptionHandledStatus(
        string $middleware = AddExceptionHandledStatus::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

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

    public function addLogs(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 10,
        string $recorder = LogRecorder::class
    ): static {
        parent::addLogs($trace, $report, $maxReported, $recorder);

        return $this;
    }

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

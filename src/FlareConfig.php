<?php

namespace Spatie\LaravelFlare;

use Monolog\Level;
use Spatie\Backtrace\Arguments\ArgumentReducers as BackTraceArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\FlareConfig as BaseFlareConfig;
use Spatie\LaravelFlare\ArgumentReducers\ArgumentReducers;
use Spatie\LaravelFlare\FlareMiddleware\AddConsoleInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionHandledStatus;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddFailedJobInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddLaravelContext;
use Spatie\LaravelFlare\FlareMiddleware\AddLaravelInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddRequestInformation;
use Spatie\LaravelFlare\Recorders\CacheRecorder\CacheRecorder;
use Spatie\LaravelFlare\Recorders\CommandRecorder\CommandRecorder;
use Spatie\LaravelFlare\Recorders\JobRecorder\FailedJobRecorder;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogRecorder;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder;
use Spatie\LaravelFlare\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\LaravelFlare\Support\FlareLogHandler;

class FlareConfig extends BaseFlareConfig
{
    public bool $sendLogsAsEvents = true;

    public Level $minimumReportLogLevel = Level::Error;

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
        );

        $config->sendLogsAsEvents = config('flare.send_logs_as_events', true);
        $config->minimumReportLogLevel = config()->has('logging.channels.flare.level')
            ? FlareLogHandler::logLevelFromName(config('logging.channels.flare.level'))
            : Level::Error;

        // TODO: Enable share button
        // TODO: application version
        //TODO: report Error Levels


        return $config;
    }

    public function useDefaults(): static
    {
        return $this
            // flare-php-client
            ->dumps()
            ->requestInfo()
            ->gitInfo()
            ->glows()
            ->solutions()
            ->stackFrameArguments()
            // laravel-flare
            ->sendLogsAsEvents()
            ->livewireComponents()
            ->laravelInfo()
            ->laravelContext()
            ->exceptionInfo()
            ->failedJobInfo()
            ->addExceptionHandledStatus()
            ->cacheEvents()
            ->logs()
            ->queries()
            ->commands()
            ->transactions();
    }

    public function sendLogsAsEvents(
        bool $sendLogsAsEvents = true,
        Level $minimumReportLogLevel = Level::Error
    ): static {
        $this->sendLogsAsEvents = $sendLogsAsEvents;
        $this->minimumReportLogLevel = $minimumReportLogLevel;

        return $this;
    }

    public function requestInfo(
        array $censorBodyFields = ['password', 'password_confirmation'],
        array $censorRequestHeaders = [
            'API-KEY',
            'Authorization',
            'Cookie',
            'Set-Cookie',
            'X-CSRF-TOKEN',
            'X-XSRF-TOKEN',
        ],
        bool $removeRequestIp = false,
        string $middleware = AddRequestInformation::class,
    ): static {
        parent::requestInfo(
            censorBodyFields: $censorBodyFields,
            censorRequestHeaders: $censorRequestHeaders,
            removeRequestIp: $removeRequestIp,
            middleware: $middleware,
        );

        return $this;
    }

    public function livewireComponents(
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

    public function consoleInfo(string $middleware = AddConsoleInformation::class): static
    {
        parent::consoleInfo(middleware: $middleware);

        return $this;
    }

    public function laravelInfo(
        string $middleware = AddLaravelInformation::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    public function laravelContext(
        string $middleware = AddLaravelContext::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    public function exceptionInfo(
        string $middleware = AddExceptionInformation::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    public function failedJobInfo(
        int $maxChainedJobReportingDepth = 5,
        string $middleware = AddFailedJobInformation::class,
        string $recorder = FailedJobRecorder::class
    ): static {
        $this->recorders[$recorder] = [
            'max_chained_job_reporting_depth' => $maxChainedJobReportingDepth,
        ];

        $this->middleware[$middleware] = [];

        return $this;
    }

    public function addExceptionHandledStatus(
        string $middleware = AddExceptionHandledStatus::class
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    public function cacheEvents(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 100,
        array $events = [SpanEventType::CacheHit, SpanEventType::CacheMiss, SpanEventType::CacheKeyWritten, SpanEventType::CacheKeyForgotten],
        string $recorder = CacheRecorder::class,
    ): static {
        parent::cacheEvents($trace, $report, $maxReported, $events, $recorder);

        return $this;
    }

    public function logs(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 10,
        string $recorder = LogRecorder::class
    ): static {
        parent::logs($trace, $report, $maxReported, $recorder);

        return $this;
    }

    public function queries(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 100,
        bool $includeBindings = true,
        bool $findOrigin = true,
        ?int $findOriginThreshold = 300_000,
        string $recorder = QueryRecorder::class,
    ): static {
        parent::queries($trace, $report, $maxReported, $includeBindings, $findOrigin, $findOriginThreshold, $recorder);

        return $this;
    }

    public function transactions(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 100,
        string $recorder = TransactionRecorder::class
    ): static {
        parent::transactions($trace, $report, $maxReported, $recorder);

        return $this;
    }

    public function stackFrameArguments(
        bool $withStackFrameArguments = true,
        BackTraceArgumentReducers|array|string|ArgumentReducer|null $argumentReducers = null,
        bool $forcePHPIniSetting = true
    ): static {
        if ($argumentReducers === null) {
            $argumentReducers = ArgumentReducers::default();
        }

        return parent::stackFrameArguments(
            $withStackFrameArguments,
            $argumentReducers,
            $forcePHPIniSetting
        );
    }

    public function commands(
        bool $trace = true,
        bool $report = true,
        string $recorder = CommandRecorder::class
    ): static {
        $this->recorders[$recorder] = [
            'trace' => $trace,
            'report' => $report,
            'max_reported' => null,
        ];

        return $this;
    }
}

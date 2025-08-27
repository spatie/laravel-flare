<?php

namespace Spatie\LaravelFlare;

use Illuminate\Support\Arr;
use Monolog\Level;
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
use Spatie\FlareClient\Api;
use Spatie\FlareClient\Contracts\FlareCollectType;
use Spatie\FlareClient\Enums\CollectType;
use Spatie\FlareClient\FlareConfig as BaseFlareConfig;
use Spatie\FlareClient\Recorders\ErrorRecorder\ErrorRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Support\TraceLimits;
use Spatie\LaravelFlare\ArgumentReducers\CollectionArgumentReducer;
use Spatie\LaravelFlare\ArgumentReducers\ModelArgumentReducer;
use Spatie\LaravelFlare\ArgumentReducers\ViewArgumentReducer;
use Spatie\LaravelFlare\Enums\LaravelCollectType;
use Spatie\LaravelFlare\Recorders\CacheRecorder\CacheRecorder;
use Spatie\LaravelFlare\Recorders\CommandRecorder\CommandRecorder;
use Spatie\LaravelFlare\Recorders\ExternalHttpRecorder\ExternalHttpRecorder;
use Spatie\LaravelFlare\Recorders\FilesystemRecorder\FilesystemRecorder;
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogRecorder;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder;
use Spatie\LaravelFlare\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\LaravelFlare\Recorders\ViewRecorder\ViewRecorder;
use Spatie\LaravelFlare\Support\CollectsResolver;
use Spatie\LaravelFlare\Support\FlareLogHandler;

class FlareConfig extends BaseFlareConfig
{
    public bool $sendLogsAsEvents = true;

    public Level $minimumReportLogLevel = Level::Error;

    public bool $logStackTraces = false;

    public bool $enableShareButton = true;

    public static function fromLaravelConfig(): self
    {
        $collects = [];

        foreach (config('flare.collects') as $type => $options) {
            $collectType = CollectType::tryFrom($type) ?? LaravelCollectType::tryFrom($type) ?? null;

            if ($type === null) {
                continue;
            }

            $collects[$collectType->value] = [
                'type' => $collectType,
                'options' => $options,
            ];
        }

        $config = new self(
            apiToken: config('flare.key'),
            baseUrl: config('flare.base_url', Api::BASE_URL),
            collects: $collects,
            reportErrorLevels: config('flare.report_error_levels'),
            applicationPath: base_path(),
            applicationName: config('app.name'),
            applicationStage: app()->environment(),
            sender: config('flare.sender.class'),
            senderConfig: config('flare.sender.config', []),
            trace: config('flare.trace'),
            sampler: config('flare.sampler.class'),
            samplerConfig: config('flare.sampler.config'),
            traceLimits: new TraceLimits(
                maxSpans: config('flare.trace_limits.max_spans'),
                maxAttributesPerSpan: config('flare.trace_limits.max_attributes_per_span'),
                maxSpanEventsPerSpan: config('flare.trace_limits.max_span_events_per_span'),
                maxAttributesPerSpanEvent: config('flare.trace_limits.max_attributes_per_span_event')
            ),
            censorClientIps: config('flare.censor.client_ips'),
            censorHeaders: config('flare.censor.headers'),
            censorBodyFields: config('flare.censor.body_fields'),
            userAttributesProvider: config('flare.attribute_providers.user'),
            collectsResolver: CollectsResolver::class,
            overriddenGroupings: config('flare.overridden_groupings'),
            includeStackTraceWithMessages: config()->get('logging.channels.flare.stack_trace', false)
        );

        $config->sendLogsAsEvents = config('flare.send_logs_as_events', true);
        $config->minimumReportLogLevel = config()->has('logging.channels.flare.level')
            ? FlareLogHandler::logLevelFromName(config('logging.channels.flare.level'))
            : Level::Error;

        $config->enableShareButton = config('flare.enable_share_button', true);

        return $config;
    }

    /**
     * @param array<FlareCollectType> $ignore ,
     * @param array<string, array<string, mixed>> $extra
     */
    public static function defaultCollects(
        array $ignore = [],
        array $extra = []
    ): array {
        $collects = [
            CollectType::Requests->value => [],
            CollectType::ErrorsWithTraces->value => [
                'with_traces' => ErrorRecorder::DEFAULT_WITH_TRACES,
            ],
            LaravelCollectType::LivewireComponents->value => [],
            CollectType::ServerInfo->value => [
                'host' => true,
                'php' => true,
                'os' => true,
                'composer' => true,
            ],
            CollectType::GitInfo->value => [],
            CollectType::Solutions->value => [
                'solution_providers' => [
                    ...FlareConfig::defaultSolutionProviders(),
                ],
            ],
            LaravelCollectType::LaravelInfo->value => [],
            LaravelCollectType::LaravelContext->value => [],
            LaravelCollectType::ExceptionContext->value => [],
            LaravelCollectType::HandledExceptions->value => [],
            CollectType::Commands->value => [
                'with_traces' => CommandRecorder::DEFAULT_WITH_TRACES,
                'with_errors' => CommandRecorder::DEFAULT_WITH_ERRORS,
                'max_items_with_errors' => CommandRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
            ],
            CollectType::Jobs->value => [
                'with_traces' => JobRecorder::DEFAULT_WITH_TRACES,
                'with_errors' => JobRecorder::DEFAULT_WITH_ERRORS,
                'max_items_with_errors' => JobRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
                'max_chained_job_reporting_depth' => JobRecorder::DEFAULT_MAX_CHAINED_JOB_REPORTING_DEPTH,
                'ignore' => [],
            ],
            CollectType::Cache->value => [
                'with_traces' => CacheRecorder::DEFAULT_WITH_TRACES,
                'with_errors' => CacheRecorder::DEFAULT_WITH_ERRORS,
                'max_items_with_errors' => CacheRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
                'operations' => CacheRecorder::DEFAULT_OPERATIONS,
            ],
            CollectType::Logs->value => [
                'with_traces' => LogRecorder::DEFAULT_WITH_TRACES,
                'with_errors' => LogRecorder::DEFAULT_WITH_ERRORS,
                'max_items_with_errors' => LogRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
                'minimal_level' => LogRecorder::DEFAULT_MINIMAL_LEVEL,
            ],
            CollectType::Queries->value => [
                'with_traces' => QueryRecorder::DEFAULT_WITH_TRACES,
                'with_errors' => QueryRecorder::DEFAULT_WITH_ERRORS,
                'max_items_with_errors' => QueryRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
                'include_bindings' => QueryRecorder::DEFAULT_INCLUDE_BINDINGS,
                'find_origin' => QueryRecorder::DEFAULT_FIND_ORIGIN,
                'find_origin_threshold' => QueryRecorder::DEFAULT_FIND_ORIGIN_THRESHOLD,
            ],
            CollectType::Transactions->value => [
                'with_traces' => TransactionRecorder::DEFAULT_WITH_TRACES,
                'with_errors' => TransactionRecorder::DEFAULT_WITH_ERRORS,
                'max_items_with_errors' => TransactionRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
            ],
            CollectType::Views->value => [
                'with_traces' => ViewRecorder::DEFAULT_WITH_TRACES,
                'with_errors' => ViewRecorder::DEFAULT_WITH_ERRORS,
            ],
            CollectType::Filesystem->value => [
                'with_traces' => FilesystemRecorder::DEFAULT_WITH_TRACES,
                'with_errors' => FilesystemRecorder::DEFAULT_WITH_ERRORS,
                'max_items_with_errors' => FilesystemRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
                'track_all_disks' => FilesystemRecorder::DEFAULT_TRACK_ALL_DISKS,
            ],
            CollectType::ExternalHttp->value => [
                'with_traces' => ExternalHttpRecorder::DEFAULT_WITH_TRACES,
                'with_errors' => ExternalHttpRecorder::DEFAULT_WITH_ERRORS,
                'max_items_with_errors' => ExternalHttpRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
            ],
            CollectType::Glows->value => [
                'with_traces' => GlowRecorder::DEFAULT_WITH_TRACES,
                'with_errors' => GlowRecorder::DEFAULT_WITH_ERRORS,
                'max_items_with_errors' => GlowRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
            ],
            CollectType::StackFrameArguments->value => [
                'argument_reducers' => static::defaultArgumentReducers(),
                'force_php_ini_setting' => true,
            ],
            CollectType::Recorders->value => [
                'recorders' => [],
            ],
            CollectType::FlareMiddleware->value => [
                'flare_middleware' => [],
            ],
        ];

        if (count($ignore) > 0) {
            $collects = Arr::except($collects, array_map(fn (FlareCollectType $type) => $type->value, $ignore));
        }

        if (count($extra) > 0) {
            return array_merge_recursive($collects, $extra);
        }

        return $collects;
    }


    public static function defaultSolutionProviders(): array
    {
        return [
            ...parent::defaultSolutionProviders(),
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
            ...parent::defaultArgumentReducers(),
            ModelArgumentReducer::class,
            CollectionArgumentReducer::class,
            ViewArgumentReducer::class,
        ];
    }

    public function useDefaults(): static
    {
        return parent::useDefaults()
            ->sendLogsAsEvents()
            ->collectLivewireComponents()
            ->collectLaravelInfo()
            ->collectLaravelContext()
            ->collectExceptionContext()
            ->collectJobs()
            ->collectHandledExceptions();
    }

    public function sendLogsAsEvents(
        bool $sendLogsAsEvents = true,
        Level $minimumReportLogLevel = Level::Error
    ): static {
        $this->sendLogsAsEvents = $sendLogsAsEvents;
        $this->minimumReportLogLevel = $minimumReportLogLevel;

        return $this;
    }


    public function collectLivewireComponents(array $extra = []): static
    {
        return $this->addCollect(LaravelCollectType::LivewireComponents, [
            'include_livewire_components' => true,
            ...$extra,
        ]);
    }

    public function ignoreLivewireComponents(array $extra = []): static
    {
        return $this->addCollect(LaravelCollectType::LivewireComponents, [
            'include_livewire_components' => false,
            ...$extra,
        ]); // Explicitly ignore livewire components
    }

    public function collectLaravelInfo(array $extra = []): static
    {
        return $this->addCollect(LaravelCollectType::LaravelInfo, $extra);
    }

    public function ignoreLaravelInfo(): static
    {
        return $this->ignoreCollect(LaravelCollectType::LaravelInfo);
    }

    public function collectLaravelContext(array $extra = []): static
    {
        return $this->addCollect(LaravelCollectType::LaravelContext, $extra);
    }

    public function ignoreLaravelContext(): static
    {
        return $this->ignoreCollect(LaravelCollectType::LaravelContext);
    }

    public function collectExceptionContext(array $extra = []): static
    {
        return $this->addCollect(LaravelCollectType::ExceptionContext, $extra);
    }

    public function ignoreExceptionContext(): static
    {
        return $this->ignoreCollect(LaravelCollectType::ExceptionContext);
    }

    public function collectHandledExceptions(array $extra = []): static
    {
        return $this->addCollect(LaravelCollectType::HandledExceptions, $extra);
    }

    public function ignoreHandledExceptions(): static
    {
        return $this->ignoreCollect(LaravelCollectType::HandledExceptions);
    }

    public function collectJobs(
        bool $withTraces = JobRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = JobRecorder::DEFAULT_WITH_ERRORS,
        ?int $maxItemsWithErrors = JobRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
        int $maxChainedJobReportingDepth = JobRecorder::DEFAULT_MAX_CHAINED_JOB_REPORTING_DEPTH
    ): static {
        return $this->addCollect(CollectType::Jobs, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            'max_items_with_errors' => $maxItemsWithErrors,
            'max_chained_job_reporting_depth' => $maxChainedJobReportingDepth,
        ]);
    }

    public function ignoreJobs(): static
    {
        return $this->ignoreCollect(CollectType::Jobs);
    }

    public function collectFilesystemOperations(
        bool $withTraces = FilesystemRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = FilesystemRecorder::DEFAULT_WITH_ERRORS,
        ?int $maxItemsWithErrors = FilesystemRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
        array $extra = [
            'track_all_disks' => FilesystemRecorder::DEFAULT_TRACK_ALL_DISKS,
        ]
    ): static {
        return parent::collectFilesystemOperations(...func_get_args());
    }
}

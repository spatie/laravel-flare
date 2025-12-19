<?php

namespace Spatie\LaravelFlare;

use BackedEnum;
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
use Spatie\FlareClient\FlareMiddleware\AddLogs;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\ArgumentReducers\CollectionArgumentReducer;
use Spatie\LaravelFlare\ArgumentReducers\ModelArgumentReducer;
use Spatie\LaravelFlare\ArgumentReducers\ViewArgumentReducer;
use Spatie\LaravelFlare\Enums\LaravelCollectType;
use Spatie\LaravelFlare\Recorders\CacheRecorder\CacheRecorder;
use Spatie\LaravelFlare\Recorders\CommandRecorder\CommandRecorder;
use Spatie\LaravelFlare\Recorders\ContextRecorder\ContextRecorder;
use Spatie\LaravelFlare\Recorders\ExternalHttpRecorder\ExternalHttpRecorder;
use Spatie\LaravelFlare\Recorders\FilesystemRecorder\FilesystemRecorder;
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;
use Spatie\LaravelFlare\Recorders\LivewireRecorder\LivewireRecorder;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder;
use Spatie\LaravelFlare\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\LaravelFlare\Recorders\ViewRecorder\ViewRecorder;

class FlareConfig extends BaseFlareConfig
{
    public bool $enableShareButton = true;

    public static function fromLaravelConfig(): self
    {
        $collects = [];

        foreach (config('flare.collects') as $type => $options) {
            $collectType = CollectType::tryFrom($type) ?? LaravelCollectType::tryFrom($type) ?? null;

            if ($collectType === null || ! is_array($options)) {
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
            applicationPath: base_path(),
            applicationName: config('app.name'),
            applicationStage: app()->environment(),
            censorClientIps: config('flare.censor.client_ips'),
            censorHeaders: config('flare.censor.headers'),
            censorBodyFields: config('flare.censor.body_fields'),
            report: config('flare.report'),
            reportErrorLevels: config('flare.report_error_levels'),
            overriddenGroupings: config('flare.overridden_groupings'),
            trace: config('flare.trace'),
            traceLimits: config('flare.trace_limits'),
            log: config('flare.log'),
            minimalLogLevel: config('flare.minimal_log_level'),
            sender: config('flare.sender.class'),
            senderConfig: config('flare.sender.config', []),
            sampler: config('flare.sampler.class'),
            samplerConfig: config('flare.sampler.config'),
            userAttributesProvider: config('flare.attribute_providers.user'),
            requestAttributesProvider: config('flare.attribute_providers.request'),
            responseAttributesProvider: config('flare.attribute_providers.response'),
            consoleAttributesProvider: config('flare.attribute_providers.console'),
        );

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
                'with_traces' => Tracer::DEFAULT_COLLECT_ERRORS_WITH_TRACES,
            ],
            LaravelCollectType::LivewireComponents->value => [
                'with_traces' => LivewireRecorder::DEFAULT_WITH_TRACES,
                'with_errors' => LivewireRecorder::DEFAULT_WITH_ERRORS,
                'max_items_with_errors' => LivewireRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
                'ignored' => LivewireRecorder::DEFAULT_IGNORED,
                'split_by_phase' => LivewireRecorder::DEFAULT_SPLIT_BY_PHASE,
            ],
            CollectType::ServerInfo->value => [
                'host' => Resource::DEFAULT_HOST_ENTITY_TYPES,
                'php' => Resource::DEFAULT_PHP_ENTITY_TYPES,
                'os' => Resource::DEFAULT_OS_ENTITY_TYPES,
                'composer' => false,
                'composer_packages' => Resource::DEFAULT_COMPOSER_PACKAGES_ENTITY_TYPES,
            ],
            CollectType::GitInfo->value => [
                'use_process' => Resource::DEFAULT_GIT_USE_PROCESS,
                'entity_types' => Resource::DEFAULT_GIT_ENTITY_TYPES,
            ],
            CollectType::Solutions->value => [
                'solution_providers' => [
                    ...FlareConfig::defaultSolutionProviders(),
                ],
            ],
            CollectType::Context->value => [],
            LaravelCollectType::LaravelInfo->value => [],
            LaravelCollectType::LaravelContext->value => [
                'include_laravel_context' => ContextRecorder::DEFAULT_INCLUDE_LARAVEL_CONTEXT,
            ],
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
            CollectType::LogsWithErrors->value => [
                'max_items' => AddLogs::DEFAULT_MAX_LOGS_WITH_ERRORS,
                'minimal_level' => AddLogs::DEFAULT_MINIMAL_LOG_LEVEL_WITH_ERRORS,
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

        foreach ($extra as $collect => $options) {
            $collects[$collect] = array_merge(
                $collects[$collect] ?? [],
                $options
            );
        }

        foreach ($ignore as $ignored) {
            if (! $ignored instanceof BackedEnum) {
                continue;
            }

            if (! array_key_exists($ignored->value, $collects)) {
                continue;
            }

            unset($collects[$ignored->value]);
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
            ->collectLivewireComponents()
            ->collectLaravelInfo()
            ->collectLaravelContext()
            ->collectExceptionContext()
            ->collectJobs()
            ->collectHandledExceptions();
    }

    public function collectLivewireComponents(
        bool $withTraces = LivewireRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = LivewireRecorder::DEFAULT_WITH_ERRORS,
        ?int $maxItemsWithErrors = LivewireRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
        array $ignored = LivewireRecorder::DEFAULT_IGNORED,
        bool $splitByPhase = LivewireRecorder::DEFAULT_SPLIT_BY_PHASE,
        array $extra = []
    ): static {
        return $this->addCollect(LaravelCollectType::LivewireComponents, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            'max_items_with_errors' => $maxItemsWithErrors,
            'ignored' => $ignored,
            'split_by_phase' => $splitByPhase,
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

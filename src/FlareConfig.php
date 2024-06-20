<?php

namespace Spatie\LaravelFlare;

use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\FlareClient\FlareConfig as BaseFlareConfig;
use Spatie\FlareClient\FlareMiddleware\AddDumps;
use Spatie\FlareClient\FlareMiddleware\AddGitInformation;
use Spatie\FlareClient\FlareMiddleware\CensorRequestBodyFields;
use Spatie\FlareClient\FlareMiddleware\CensorRequestHeaders;
use Spatie\FlareClient\FlareMiddleware\RemoveRequestIp;
use Spatie\LaravelFlare\ArgumentReducers\CollectionArgumentReducer;
use Spatie\LaravelFlare\ArgumentReducers\ModelArgumentReducer;
use Spatie\LaravelFlare\ContextProviders\LaravelContextProviderDetector;
use Spatie\LaravelFlare\FlareMiddleware\AddEnvironmentInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionHandledStatus;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddJobs;
use Spatie\LaravelFlare\FlareMiddleware\AddLaravelContext;
use Spatie\LaravelFlare\FlareMiddleware\AddLogs;
use Spatie\LaravelFlare\FlareMiddleware\AddNotifierName;
use Spatie\LaravelFlare\FlareMiddleware\AddQueries;

class FlareConfig extends BaseFlareConfig
{
    protected bool $sendLogsAsEvents = true;

    public static function fromLaravelConfig(): self
    {
        $argumentReducers = ArgumentReducers::create(config(
            'flare.argument_reducers', ArgumentReducers::default([
            CollectionArgumentReducer::class,
            ModelArgumentReducer::class,
        ])));

        $config = new self(
            apiToken: config('flare.key'),
            baseUrl: config('flare.base_url', 'https://flareapp.io/api'),
            timeout: config('flare.timeout', 10),
            applicationPath: base_path(),
            contextProviderDetector: LaravelContextProviderDetector::class,
            applicationName: config('app.name'),
            applicationStage: app()->environment(),
            argumentReducers: $argumentReducers,
            withStackFrameArguments: config('flare.with_stack_frame_arguments', true),
            forcePHPStackFrameArgumentsIniSetting: config('flare.force_php_stack_frame_arguments_ini_setting', true),
            sender: config('flare.sender'),
            solutionsProviders: config('flare.solution_providers', []),
        );

        foreach (config('flare.middleware') as $key => $value) {
            [$middleware, $options] = match (true) {
                is_numeric($key) && is_string($value) => [$value, []],
                is_string($key) && is_array($value) => [$key, $value],
                default => [null, null],
            };

            match ($middleware) {
                RemoveRequestIp::class => $config->removeRequestIp(),
                AddGitInformation::class => $config->addGitInfo(),
                AddEnvironmentInformation::class => $config->addEnvironmentInfo(),
                AddNotifierName::class => $config->addNotifierName(),
                AddExceptionInformation::class => $config->addExceptionInfo(),
                AddLogs::class => $config->addLogs(
                    maxLogs: $options['maximum_number_of_collected_logs'] ?? 200,
                    traceLogs: $options['trace_logs'] ?? false,
                ),
                AddDumps::class => $config->addDumps(
                    maxDumps: $options['maximum_number_of_collected_dump'] ?? 200,
                    traceDumps: $options['trace_dumps'] ?? false,
                    traceDumpOrigins: $options['trace_dump_origins'] ?? false,
                ),
                AddQueries::class => $config->addQueries(
                    reportQueryBindings: $options['report_query_bindings'] ?? true,
                    maximumNumberOfCollectedQueries: $options['maximum_number_of_collected_queries'] ?? 200,
                    traceQueryOriginThreshold: $options['trace_query_origin_threshold'] ?? 300,
                ),
                AddJobs::class => $config->addJobs(
                    maxChainedJobReportingDepth: $options['max_chained_job_reporting_depth'] ?? 5,
                ),
                AddLaravelContext::class => $config->addLaravelContext(),
                AddExceptionHandledStatus::class => $config->addExceptionHandledStatus(),
                CensorRequestBodyFields::class => $config->censorRequestBodyFields(
                    fieldNames: $options['censor_fields'] ?? ['password', 'password_confirmation'],
                ),
                CensorRequestHeaders::class => $config->censorRequestHeaders(
                    headers: $options['headers'] ?? [
                        'API-KEY',
                        'Authorization',
                        'Cookie',
                        'Set-Cookie',
                        'X-CSRF-TOKEN',
                        'X-XSRF-TOKEN',
                    ],
                ),
                default => $config->middleware(new $middleware($options)),
            };
        }

        return $config;
    }

    public function sendLogsAsEvents(bool $sendLogsAsEvents = true): static
    {
        $this->sendLogsAsEvents = $sendLogsAsEvents;

        return $this;
    }

    public function addNotifierName(): static
    {
        $this->middleware(new AddNotifierName());

        return $this;
    }

    public function addEnvironmentInfo(): static
    {
        $this->middleware(new AddEnvironmentInformation());

        return $this;
    }

    public function addExceptionInfo(): static
    {
        $this->middleware(new AddExceptionInformation());

        return $this;
    }

    public function addLogs(
        int $maxLogs = 200,
        bool $traceLogs = false
    ): static {
        $this->middleware(new AddLogs(
            maxLogs: $maxLogs,
            traceLogs: $traceLogs,
        ));

        return $this;
    }

    public function addQueries(
        bool $reportQueryBindings = true,
        int $maximumNumberOfCollectedQueries = 200,
        int $traceQueryOriginThreshold = 300
    ): static {
        $this->middleware(new AddQueries(
            reportBindings: $reportQueryBindings,
            maxQueries: $maximumNumberOfCollectedQueries,
            traceQueryOriginThreshold: $traceQueryOriginThreshold,
        ));

        return $this;
    }

    public function addJobs(
        int $maxChainedJobReportingDepth = 5
    ): static {
        $this->middleware(new AddJobs($maxChainedJobReportingDepth));

        return $this;
    }

    public function addLaravelContext(): static
    {
        $this->middleware(new AddLaravelContext());

        return $this;
    }

    public function addExceptionHandledStatus(): static
    {
        $this->middleware(new AddExceptionHandledStatus());

        return $this;
    }
}

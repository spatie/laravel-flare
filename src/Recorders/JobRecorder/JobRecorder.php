<?php

namespace Spatie\LaravelFlare\Recorders\JobRecorder;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Psr\Container\ContainerInterface;
use Spatie\Backtrace\Arguments\ReduceArgumentPayloadAction;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\LifecycleStage;
use Spatie\FlareClient\Recorders\JobRecorder\JobRecorder as BaseJobRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelJobAttributesProvider;
use Spatie\LaravelFlare\Jobs\SendFlarePayload;

class JobRecorder extends BaseJobRecorder
{
    public const DEFAULT_MAX_CHAINED_JOB_REPORTING_DEPTH = 2;

    public const INTERNAL_IGNORED_JOBS = [
        SendFlarePayload::class,
    ];

    protected int $maxChainedJobReportingDepth = self::DEFAULT_MAX_CHAINED_JOB_REPORTING_DEPTH;

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        EntryPointResolver $entryPointResolver,
        Lifecycle $lifecycle,
        protected Dispatcher $dispatcher,
        protected ReduceArgumentPayloadAction $reduceArgumentPayloadAction,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $entryPointResolver, $lifecycle, $config);
    }

    protected function configure(array $config): void
    {
        parent::configure($config);

        $this->maxChainedJobReportingDepth = $config['maxChainedJobReportingDepth'] ?? self::DEFAULT_MAX_CHAINED_JOB_REPORTING_DEPTH;
    }

    public function boot(): void
    {
        $this->dispatcher->listen(JobProcessing::class, [$this, 'recordProcessing']);
        $this->dispatcher->listen(JobProcessed::class, [$this, 'recordProcessed']);
        $this->dispatcher->listen(JobExceptionOccurred::class, [$this, 'recordExceptionOccurred']);
    }

    public function recordProcessing(JobProcessing $event): ?Span
    {
        $traceparent = $event->job->payload()[Ids::FLARE_TRACE_PARENT] ?? null;

        return $this->recordStart(
            jobAttributesProvider: new LaravelJobAttributesProvider(
                $this->reduceArgumentPayloadAction,
                $event->job,
                $event->connectionName,
                $this->maxChainedJobReportingDepth,
            ),
            traceparent: $traceparent,
        );
    }

    public function recordProcessed(JobProcessed $event): ?Span
    {
        return $this->recordEnd([
            'laravel.job.success' => true,
            'laravel.job.released' => $event->job->isReleased(),
            'laravel.job.deleted' => $event->job->isDeleted(),
        ]);
    }

    public function recordExceptionOccurred(JobExceptionOccurred $event): ?Span
    {
        $span = $this->recordFailed($event->exception, [
            'laravel.job.success' => false,
            'laravel.job.released' => $event->job->isReleased(),
            'laravel.job.deleted' => $event->job->isDeleted(),
        ]);

        // dispatchAfterResponse runs in the main HTTP lifecycle (Terminating stage). When the
        // job throws, Laravel renders the error and never reaches `terminated()`, so we force it
        // here to flush the report.
        if ($this->lifecycle->getStage() === LifecycleStage::Terminating) {
            $this->lifecycle->terminated();
        }

        return $span;
    }

    /** @return array<int, class-string> */
    protected function defaultIgnoredJobClasses(): array
    {
        return self::INTERNAL_IGNORED_JOBS;
    }
}

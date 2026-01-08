<?php

namespace Spatie\LaravelFlare\Recorders\JobRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelJobAttributesProvider;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\LaravelFlare\FlareMiddleware\AddJobInformation;
use Spatie\LaravelFlare\Jobs\SendFlarePayload;

class JobRecorder extends SpansRecorder
{
    protected int $maxChainedJobReportingDepth = 0;

    public const DEFAULT_MAX_CHAINED_JOB_REPORTING_DEPTH = 2;

    public const INTERNAL_IGNORED_JOBS = [
        SendFlarePayload::class,
    ];

    /** @var array<class-string> */
    protected array $ignore = [];

    // TODO: test this on vapor:
    // 1) Create a job with error
    // 2) We do all tracing correctly and store some breadcrumbs
    // 3) The subtask ends, so the error is sent
    // 4) We're now in limbo
    // 5) Error handling happens and a report is stored within the API
    // 6) The shutdown function probably won't run, lifecycle won't flush the latest data
    // 7) Our error is lost
    // Also test tracing and exceptions on regular requests and jobs

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        protected LaravelJobAttributesProvider $laravelJobAttributesProvider,
        protected LifeCycle $lifecycle,
        array $config
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    protected function configure(array $config): void
    {
        $this->maxChainedJobReportingDepth = $config['maxChainedJobReportingDepth'] ?? 2;

        $this->ignore = self::INTERNAL_IGNORED_JOBS;

        if (array_key_exists('ignore', $config)) {
            array_push($this->ignore, ...$config['ignore']);
        }
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Job;
    }

    public function boot(): void
    {
        $this->dispatcher->listen(JobProcessing::class, [$this, 'recordProcessing']);
        $this->dispatcher->listen(JobProcessed::class, [$this, 'recordProcessed']);
        $this->dispatcher->listen(JobExceptionOccurred::class, [$this, 'recordExceptionOccurred']);
    }

    public function recordProcessing(JobProcessing $event): ?Span
    {
        AddJobInformation::clearLatestJobInfo();

        $traceparent = $event->job->payload()[Ids::FLARE_TRACE_PARENT] ?? null;

        $shouldIgnore = $this->shouldIgnore($event->job);

        if ($shouldIgnore) {
            $traceparent = $this->tracer->ids->setTraceparentSampling($traceparent, false);
        }

        $this->lifecycle->startSubtask(traceparent: $traceparent);

        if ($this->shouldIgnore($event->job)) {
            return null;
        }

        $attributes = $this->laravelJobAttributesProvider->toArray(
            $event->job,
            $event->connectionName,
            $this->maxChainedJobReportingDepth
        );

        $jobName = $attributes['laravel.job.name'] ?? $attributes['laravel.job.class'] ?? 'Unknown';

        return $this->startSpan(
            name: "Job - {$jobName}",
            attributes: [
                'flare.span_type' => SpanType::Job,
                ...$attributes,
            ],
        );
    }

    public function recordProcessed(JobProcessed $event): void
    {
        if ($this->shouldIgnore($event->job)) {
            $this->lifecycle->endSubtask();

            return;
        }

        $this->endSpan(additionalAttributes: [
            'laravel.job.success' => true,
            'laravel.job.released' => $event->job->isReleased(),
            'laravel.job.deleted' => $event->job->isDeleted(),
        ], includeMemoryUsage: true);

        $this->lifecycle->endSubtask();
    }

    public function recordExceptionOccurred(JobExceptionOccurred $event): void
    {
        if ($this->shouldIgnore($event->job)) {
            $this->lifecycle->endSubtask();

            return;
        }

        // Error handling is performed after, so leave breadcrumbs
        // only do this on a queue worker since otherwise it may interfere
        // with errors on the main request lifecycle
        $shouldLeaveBreadcrumbs = $this->lifecycle->usesSubtasks;

        $trackingUuid = null;

        if ($shouldLeaveBreadcrumbs) {
            AddJobInformation::setUsedTrackingUuid($trackingUuid = $this->tracer->ids->uuid());
        }

        $throwableClass = $event->exception::class;

        $span = $this->endSpan(
            additionalAttributes: [
                'laravel.job.success' => false,
                'laravel.job.released' => $event->job->isReleased(),
                'laravel.job.deleted' => $event->job->isDeleted(),
            ],
            spanCallback: fn (Span $span) => $span
                ->setStatus(SpanStatusCode::Error, $event->exception->getMessage())
                ->addEvent(
                    new SpanEvent(
                        name: "Exception - {$throwableClass}",
                        timestamp: $this->tracer->time->getCurrentTime(),
                        attributes: [
                            'flare.span_event_type' => SpanEventType::Exception,
                            'exception.message' => $event->exception->getMessage(),
                            'exception.type' => $throwableClass,
                            'exception.handled' => null,
                            'exception.id' => $trackingUuid,
                        ]
                    )
                ),
            includeMemoryUsage: true
        );

        if ($span !== null && $shouldLeaveBreadcrumbs) {
            AddJobInformation::setLatestJob($span);
        }

        $this->lifecycle->endSubtask();
    }

    protected function shouldIgnore(Job $job): bool
    {
        $class = $job->payload()['data']['commandName'] ?? null;

        return in_array($class, $this->ignore);
    }
}

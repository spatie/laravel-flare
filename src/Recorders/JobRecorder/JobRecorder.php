<?php

namespace Spatie\LaravelFlare\Recorders\JobRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Recorders\ThrowableRecorder\ThrowableSpanEvent;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelJobAttributesProvider;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\LaravelFlare\FlareMiddleware\AddJobInformation;

class JobRecorder  extends Recorder implements SpansRecorder
{
    /** @use RecordsPendingSpans<Span> */
    use RecordsPendingSpans;

    protected int $maxChainedJobReportingDepth = 0;

    public const DEFAULT_MAX_CHAINED_JOB_REPORTING_DEPTH = 2;

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        protected LaravelJobAttributesProvider $laravelJobAttributesProvider,
        array $config
    ) {
        $this->configure($config);

        $this->maxChainedJobReportingDepth = $config['maxChainedJobReportingDepth'] ?? 2;
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Job;
    }

    public function start(): void
    {
        $this->dispatcher->listen(JobProcessing::class, [$this, 'recordProcessing']);
        $this->dispatcher->listen(JobProcessed::class, [$this, 'recordProcessed']);
        $this->dispatcher->listen(JobExceptionOccurred::class, [$this, 'recordExceptionOccurred']);
    }

    public function recordProcessing(JobProcessing $event): ?Span
    {
        $attributes = $this->laravelJobAttributesProvider->toArray(
            $event->job,
            $event->connectionName,
            $this->maxChainedJobReportingDepth
        );

        AddJobInformation::$currentJob = $attributes;

        $this->tryToResumeTrace($event);

        return $this->startSpan(function () use ($attributes) {
            return Span::build(
                traceId: $this->tracer->currentTraceId() ?? '',
                parentId: $this->tracer->currentSpanId(),
                name: "Job - {$attributes['laravel.job.name']}",
                attributes: $attributes + [
                    'flare.span_type' => SpanType::Job,
                ],
            );
        });
    }

    public function recordProcessed(JobProcessed $event): void
    {
        $this->endSpan(attributes: [
            'laravel.job.success' => true,
        ]);

        AddJobInformation::$currentJob = null;
    }

    public function recordExceptionOccurred(JobExceptionOccurred $event): void
    {
        $this->endSpan(closure: fn (Span $span) => $span->addEvent(
            ThrowableSpanEvent::fromThrowable($event->exception)
        ), attributes: [
            'laravel.job.success' => false,
        ]);

        AddJobInformation::$currentJob = null;
    }

    protected function tryToResumeTrace(
        JobProcessing $event
    ): void {
        $traceParent = $event->job->payload()[Ids::FLARE_TRACE_PARENT] ?? null;

        if ($traceParent === null) {
            return;
        }

        $samplingType = $this->tracer->potentiallyResumeTrace($traceParent);

        if ($samplingType === SamplingType::Sampling) {
            $this->shouldEndTrace = true;
        }
    }

    protected function canStartTraces(): bool
    {
        return true;
    }
}

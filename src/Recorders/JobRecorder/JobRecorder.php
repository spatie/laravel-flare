<?php

namespace Spatie\LaravelFlare\Recorders\JobRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Recorders\ErrorRecorder\ErrorSpanEvent;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Recorders\ThrowableRecorder\ThrowableSpanEvent;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelJobAttributesProvider;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\LaravelFlare\FlareMiddleware\AddJobInformation;

class JobRecorder extends Recorder implements SpansRecorder
{
    /** @use RecordsSpans<Span> */
    use RecordsSpans;

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

    public function boot(): void
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

        return $this->startSpan(
            name: "Job - {$attributes['laravel.job.name']}",
            attributes: [
                'flare.span_type' => SpanType::Job,
                ...$attributes,
            ]
        );
    }

    public function recordProcessed(JobProcessed $event): void
    {
        $this->endSpan(additionalAttributes: [
            'laravel.job.success' => true,
        ]);

        AddJobInformation::$currentJob = null;
    }

    public function recordExceptionOccurred(JobExceptionOccurred $event): void
    {
        $this->endSpan(additionalAttributes: [
            'laravel.job.success' => false,
        ], spanCallback: fn (Span $span) => $span->addEvent(
            ErrorSpanEvent::fromThrowable($event->exception, $this->tracer->time->getCurrentTime())
        ));

        AddJobInformation::$currentJob = null;
    }

    protected function tryToResumeTrace(
        JobProcessing $event
    ): void {
        $traceParent = $event->job->payload()[Ids::FLARE_TRACE_PARENT] ?? null;

        if ($traceParent === null) {
            return;
        }

        $samplingType = $this->tracer->startTrace($traceParent);

        if ($samplingType === SamplingType::Sampling) {
            $this->shouldEndTrace = true;
        }
    }

    protected function canStartTraces(): bool
    {
        return true;
    }
}

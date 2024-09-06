<?php

namespace Spatie\LaravelFlare\Recorders\JobRecorder;

use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Recorders\ThrowableRecorder\ThrowableSpanEvent;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelJobAttributesProvider;
use Spatie\LaravelFlare\Enums\SpanType;

class JobRecorder implements Recorder
{
    use RecordsPendingSpans;

    protected int $maxChainedJobReportingDepth = 0;

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

        return $this->startSpan(function () use ($attributes, $event) {
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
    }

    public function recordExceptionOccurred(JobExceptionOccurred $event): void
    {
        $this->tracer->currentSpan()->addEvent(
            ThrowableSpanEvent::fromThrowable($event->exception)
        );

        $this->endSpan(attributes: [
            'laravel.job.success' => false,
        ]);
    }

    protected function canStartTraces(): bool
    {
        return true;
    }
}

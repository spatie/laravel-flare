<?php

namespace Spatie\LaravelFlare\Recorders\QueueRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelJobAttributesProvider;

class QueueRecorder implements SpansRecorder
{
    use RecordsPendingSpans;

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        protected LaravelJobAttributesProvider $laravelJobAttributesProvider,
        array $config
    ) {
        $this->configure($config);
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Queue;
    }

    public function start(): void
    {
        $this->dispatcher->listen(JobQueueing::class, [$this, 'recordQueuing']);
        $this->dispatcher->listen(JobQueued::class, [$this, 'recordQueued']);
    }

    public function recordQueuing(
        JobQueueing $event,
    ): ?Span {
        return $this->startSpan(function () use ($event) {
            $attributes = [
                'laravel.job.queue.connection_name' => $event->connectionName,
                'laravel.job.queue.name' => $event->queue ?? config('queue.default'),
                ...$this->laravelJobAttributesProvider->getJobPropertiesFromPayload($event->payload()),
            ];

            return Span::build(
                traceId: $this->tracer->currentTraceId() ?? '',
                parentId: $this->tracer->currentSpanId(),
                name: "Queueing -  {$attributes['laravel.job.name']}",
                attributes: $attributes
            );
        });
    }

    public function recordQueued(
        JobQueued $event,
    ): ?Span {
        return $this->endSpan();
    }
}

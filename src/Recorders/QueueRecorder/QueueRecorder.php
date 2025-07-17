<?php

namespace Spatie\LaravelFlare\Recorders\QueueRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Queue;
use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelJobAttributesProvider;
use Spatie\LaravelFlare\Enums\SpanType;

class QueueRecorder implements SpansRecorder
{
    /** @use RecordsSpans<Span> */
    use RecordsSpans;

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

    public function boot(): void
    {
        Queue::createPayloadUsing(function (?string $connection, ?string $queue, ?array $payload): ?array {
            if ($this->tracer->isSampling() === false) {
                return $payload;
            }

            if ($payload === null) {
                return $payload;
            }

            if ($this->isSyncConnection($connection)) {
                return $payload;
            }

            $this->startSpan(nameAndAttributes: function () use ($payload, $queue, $connection) {
                $attributes = [
                    'flare.span_type' => SpanType::Queueing,
                    'laravel.job.queue.connection_name' => $connection,
                    'laravel.job.queue.name' => $queue,
                    ...$this->laravelJobAttributesProvider->getJobPropertiesFromPayload($payload),
                ];

                return [
                    'name' => "Queueing - {$attributes['laravel.job.name']}",
                    'attributes' => $attributes,
                ];
            });

            $payload[Ids::FLARE_TRACE_PARENT] = $this->tracer->traceParent();

            return $payload;
        });


        $this->dispatcher->listen(JobQueued::class, [$this, 'recordQueued']);
    }

    public function recordQueued(
        JobQueued $event,
    ): ?Span {
        return $this->endSpan();
    }

    protected function isSyncConnection(?string $connection): bool
    {
        if ($connection === null) {
            return false;
        }

        return config()->get("queue.connections.{$connection}.driver") === 'sync';
    }
}

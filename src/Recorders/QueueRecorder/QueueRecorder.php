<?php

namespace Spatie\LaravelFlare\Recorders\QueueRecorder;

use Illuminate\Contracts\Events\Dispatcher;
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
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;

class QueueRecorder implements SpansRecorder
{
    /** @use RecordsSpans<Span> */
    use RecordsSpans;

    /** @var array<class-string> */
    protected array $ignore;

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        protected LaravelJobAttributesProvider $laravelJobAttributesProvider,
        array $config
    ) {
        $this->configure($config);

        $this->ignore = JobRecorder::INTERNAL_IGNORED_JOBS;

        if (array_key_exists('ignore', $config)) {
            array_push($this->ignore, ...$config['ignore']);
        }
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

            if ($this->isIgnored($payload)) {
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
        if ($this->isIgnored($event->payload())) {
            return null;
        }

        return $this->endSpan();
    }

    protected function isSyncConnection(?string $connection): bool
    {
        if ($connection === null) {
            return false;
        }

        return config()->get("queue.connections.{$connection}.driver") === 'sync';
    }

    protected function isIgnored(array $payload): bool
    {
        $class = $payload['displayName'] ?? null;

        if ($class === null) {
            return false;
        }

        return in_array($class, $this->ignore);
    }
}

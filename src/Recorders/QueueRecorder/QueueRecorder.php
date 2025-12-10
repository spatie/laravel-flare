<?php

namespace Spatie\LaravelFlare\Recorders\QueueRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Queue;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelJobAttributesProvider;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;

class QueueRecorder extends SpansRecorder
{
    /** @var array<class-string> */
    protected array $ignore;

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        protected LaravelJobAttributesProvider $laravelJobAttributesProvider,
        array $config
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    protected function configure(array $config): void
    {
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
            if ($payload === null
                || $this->withTraces === false
                || $this->tracer->disabled === true
                || $this->tracer->sampling === false
            ) {
                return $payload;
            }

            if ($this->tracer->isSampling() === false || $this->isIgnored($payload)) {
                $payload[Ids::FLARE_TRACE_PARENT] = $this->tracer->traceParent();

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

                $jobName = $attributes['laravel.job.name'] ?? $attributes['laravel.job.class'] ?? 'Unknown';

                return [
                    'name' => "Queueing - {$jobName}",
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

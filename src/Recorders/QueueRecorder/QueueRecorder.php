<?php

namespace Spatie\LaravelFlare\Recorders\QueueRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Queue;
use Spatie\Backtrace\Arguments\ReduceArgumentPayloadAction;
use Spatie\FlareClient\Recorders\QueueRecorder\QueueRecorder as BaseQueueRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelQueuedJobAttributesProvider;
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;

class QueueRecorder extends BaseQueueRecorder
{
    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        protected ReduceArgumentPayloadAction $reduceArgumentPayloadAction,
        array $config
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public function boot(): void
    {
        Queue::createPayloadUsing(function (?string $connection, ?string $queue, ?array $payload): ?array {
            if ($payload === null) {
                return $payload;
            }

            if ($this->isSyncConnection($connection)) {
                return $payload;
            }

            if ($this->withTraces === false
                || $this->tracer->disabled === true
                || $this->tracer->isSampling() === false
            ) {
                $payload[Ids::FLARE_TRACE_PARENT] = $this->tracer->traceParent();

                return $payload;
            }

            $provider = new LaravelQueuedJobAttributesProvider(
                $this->reduceArgumentPayloadAction,
                $payload,
                $connection,
                $queue,
            );

            $this->recordStart($provider);

            $payload[Ids::FLARE_TRACE_PARENT] = $this->tracer->traceParent();

            // Batched jobs never dispatch a JobQueued event so close the span immediately.
            if ($provider->isBatched()) {
                $this->recordEnd();
            }

            return $payload;
        });

        $this->dispatcher->listen(JobQueued::class, [$this, 'recordQueued']);
    }

    public function recordQueued(JobQueued $event): ?Span
    {
        return $this->recordEnd();
    }

    protected function isSyncConnection(?string $connection): bool
    {
        if ($connection === null) {
            return false;
        }

        return config()->get("queue.connections.{$connection}.driver") === 'sync';
    }

    /** @return array<int, class-string> */
    protected function defaultIgnoredJobClasses(): array
    {
        return JobRecorder::INTERNAL_IGNORED_JOBS;
    }
}

<?php

namespace Spatie\LaravelFlare\Recorders\QueryRecorder;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Spatie\FlareClient\Performance\Tracer;

class QueryRecorder
{
    /** @var QuerySpan[] */
    protected array $spans = [];

    public function __construct(
        protected Application $app,
        protected Tracer $tracer,
        protected bool $reportBindings = true,
        protected ?int $maxQueries = 200,
        protected ?int $traceQueryOriginThreshold = 300,
    ) {
        $this->traceQueryOriginThreshold *= 1000_000; // Milliseconds to microseconds
    }

    public function start(): self
    {
        $this->app['events']->listen(QueryExecuted::class, [$this, 'record']);

        return $this;
    }

    public function record(QueryExecuted $queryExecuted): void
    {
        $span = $this->buildSpan($queryExecuted);

        $this->spans[] = $span;

        if ($this->tracer->isSamping()) {
            $this->tracer->addSpan($span);
        }

        if ($this->maxQueries && count($this->spans) > $this->maxQueries) {
            $this->removeOldestSpan();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getQueries(): array
    {
        // TODO: maybe embrace the span format for error reporting

        $queries = [];

        foreach ($this->spans as $query) {
            $queries[] = $query->toOriginalFlareFormat();
        }

        return $queries;
    }

    /** @return QuerySpan[] */
    public function getSpans(): array
    {
        return $this->spans;
    }

    public function reset(): void
    {
        $this->spans = [];
    }

    protected function buildSpan(QueryExecuted $queryExecuted): QuerySpan
    {
        $isSampling = $this->tracer->isSamping();

        $duration = $queryExecuted->time * 1000_000;

        $span = new QuerySpan(
            traceId: $isSampling ? $this->tracer->currentTraceId() : '',
            parentSpanId: $isSampling ? $this->tracer->currentSpanId() : '',
            sql: $queryExecuted->sql,
            duration: $duration,
            bindings: $this->reportBindings ? $queryExecuted->bindings : null,
            databaseName: $queryExecuted->connection->getDatabaseName(),
            driverName: $queryExecuted->connection->getDriverName(),
            connectionName: $queryExecuted->connectionName,
        );

        if (! $this->shouldTraceOrigins($duration)) {
            return $span;
        }

        $frame = $this->tracer->backTracer->firstApplicationFrame(20);

        if ($frame) {
            $span->setOriginFrame($frame);
        }

        return $span;
    }

    protected function shouldTraceOrigins(int $duration): bool
    {
        return $this->tracer->isSamping()
            && $this->tracer->currentSpanId()
            && $this->traceQueryOriginThreshold !== null
            && $duration >= $this->traceQueryOriginThreshold;
    }

    protected function removeOldestSpan(): void
    {
        $span = array_shift($this->spans);

        if ($this->tracer->isSamping()) {
            unset($this->tracer[$span->traceId][$span->spanId]);
        }
    }
}

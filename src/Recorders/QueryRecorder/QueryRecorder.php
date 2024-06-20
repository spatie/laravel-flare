<?php

namespace Spatie\LaravelFlare\Recorders\QueryRecorder;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Concerns\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\Performance\Tracer;


class QueryRecorder implements Recorder
{
    /**  @use RecordsSpans<QuerySpan> */
    use RecordsSpans;

    public function __construct(
        protected Application $app,
        protected Tracer $tracer,
        protected bool $reportBindings,
        ?int $maxQueries,
        protected ?int $traceQueryOriginThreshold,
    ) {
        $this->traceQueryOriginThreshold *= 1000_000; // Milliseconds to microseconds
        $this->maxEntries = $maxQueries;
    }

    public function start(): void
    {
        $this->app['events']->listen(QueryExecuted::class, [$this, 'record']);
    }

    public function record(QueryExecuted $queryExecuted): void
    {
        $this->persistSpan($this->buildSpan($queryExecuted));
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
        return $this->shouldTraceSpans()
            && $this->traceQueryOriginThreshold !== null
            && $duration >= $this->traceQueryOriginThreshold;
    }
}

<?php

namespace Spatie\LaravelFlare\Recorders\QueryRecorder;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Concerns\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder as BaseQueryRecorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QuerySpan;
use Spatie\FlareClient\Time\Duration;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Concerns\LaravelRegisteredRecorder;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\FlareClient\Support\BackTracer;


class QueryRecorder extends BaseQueryRecorder
{
    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        ?array $config = null,
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public function start(): void
    {
        $this->dispatcher->listen(QueryExecuted::class, [$this, 'recordEvent']);
    }

    public function recordEvent(QueryExecuted $event): ?QuerySpan
    {
        return $this->record(
            $event->sql,
            Duration::milliseconds($event->time),
            $event->bindings,
            $event->connection->getDatabaseName(),
            $event->connection->getDriverName(),
            SpanType::Query,
            [
                'laravel.db.connection' => $event->connectionName,
            ]
        );
    }
}

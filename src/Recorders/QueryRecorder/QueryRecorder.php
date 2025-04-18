<?php

namespace Spatie\LaravelFlare\Recorders\QueryRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder as BaseQueryRecorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QuerySpan;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\FlareClient\Tracer;

class QueryRecorder extends BaseQueryRecorder
{
    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        array $config,
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
            sql: $event->sql,
            duration: TimeHelper::milliseconds($event->time),
            bindings: $event->bindings,
            databaseName: $event->connection->getDatabaseName(),
            driverName: $event->connection->getDriverName(),
            attributes: [
                'laravel.db.connection' => $event->connectionName,
            ]
        );
    }
}

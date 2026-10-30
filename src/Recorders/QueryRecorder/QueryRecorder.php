<?php

namespace Spatie\LaravelFlare\Recorders\QueryRecorder;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\QueryExecuted;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder as BaseQueryRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\FlareClient\Tracer;
use Throwable;

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

    public function boot(): void
    {
        $this->dispatcher->listen(QueryExecuted::class, [$this, 'recordEvent']);
        $this->dispatcher->listen(ConnectionEstablished::class, [$this, 'recordConnection']);
    }

    public function recordConnection(ConnectionEstablished $event): void
    {
        $connection = $event->connection;

        if ($connection->transactionLevel() > 0) {
            return;
        }

        $pdo = $connection->getRawPdo();
        if ($pdo instanceof Closure) {
            $connection->setPdo($this->traceConnection($pdo, $connection, 'write'));
        }

        $readPdo = $connection->getRawReadPdo();
        if ($readPdo instanceof Closure) {
            $connection->setReadPdo($this->traceConnection($readPdo, $connection, 'read'));
        }

        if (method_exists($connection, 'getRawDirectPdo') && method_exists($connection, 'setDirectPdo')) {
            $directPdo = $connection->getRawDirectPdo();
            if ($directPdo instanceof Closure) {
                $connection->setDirectPdo($this->traceConnection($directPdo, $connection, 'direct'));
            }
        }
    }

    protected function traceConnection(Closure $resolver, Connection $connection, string $role): Closure
    {
        return function () use ($resolver, $connection, $role) {
            $span = $this->startSpan(
                name: 'Database connection',
                attributes: [
                    'flare.span_type' => SpanType::Custom,
                    'db.system' => $connection->getDriverName(),
                    'laravel.db.connection' => $connection->getName(),
                    'laravel.db.connection.role' => $role,
                ],
            );

            try {
                return $resolver();
            } catch (Throwable $exception) {
                $span?->setStatus(SpanStatusCode::Error);

                throw $exception;
            } finally {
                if ($span !== null) {
                    $this->endSpan();
                }
            }
        };
    }

    public function recordEvent(QueryExecuted $event): ?Span
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

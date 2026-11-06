<?php

namespace Spatie\LaravelFlare\Recorders\DatabaseConnectionRecorder;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Spatie\FlareClient\Recorders\DatabaseConnectionRecorder\DatabaseConnectionRecorder as BaseDatabaseConnectionRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Throwable;

class DatabaseConnectionRecorder extends BaseDatabaseConnectionRecorder
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
        $this->dispatcher->listen(ConnectionEstablished::class, [$this, 'recordConnection']);
    }

    public function recordConnection(ConnectionEstablished $event): void
    {
        $connection = $event->connection;

        if ($connection->transactionLevel() > 0) {
            return;
        }

        $this->wrapPdoResolver($connection, 'Pdo', 'write');
        $this->wrapPdoResolver($connection, 'ReadPdo', 'read');
        $this->wrapPdoResolver($connection, 'DirectPdo', 'direct');
    }

    protected function wrapPdoResolver(Connection $connection, string $accessor, string $role): void
    {
        $getter = "getRaw{$accessor}";
        $setter = "set{$accessor}";

        if (! method_exists($connection, $getter) || ! method_exists($connection, $setter)) {
            return;
        }

        $pdo = $connection->{$getter}();

        if (! $pdo instanceof Closure) {
            return;
        }

        $connection->{$setter}($this->traceConnection($pdo, $connection, $role));
    }

    protected function traceConnection(Closure $resolver, Connection $connection, string $role): Closure
    {
        return function () use ($resolver, $connection, $role) {
            $span = $this->recordStart(
                databaseName: $connection->getDatabaseName(),
                driverName: $connection->getDriverName(),
                attributes: [
                    'laravel.db.connection' => $connection->getName(),
                    'laravel.db.connection.role' => $role,
                ],
            );

            try {
                $pdo = $resolver();
            } catch (Throwable $exception) {
                if ($span !== null) {
                    $this->recordFailed();
                }

                throw $exception;
            }

            if ($span !== null) {
                $this->recordEnd();
            }

            return $pdo;
        };
    }
}

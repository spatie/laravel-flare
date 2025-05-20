<?php

namespace Spatie\LaravelFlare\Recorders\TransactionRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Spatie\FlareClient\Recorders\TransactionRecorder\TransactionRecorder as BaseTransactionRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;

class TransactionRecorder extends BaseTransactionRecorder
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
        $this->dispatcher->listen(TransactionBeginning::class, fn (TransactionBeginning $event) => $this->recordBegin(
            attributes: ['laravel.db.connection' => $event->connectionName]
        ));
        $this->dispatcher->listen(TransactionCommitted::class, fn (TransactionCommitted $event) => $this->recordCommit(
            attributes: ['laravel.db.connection' => $event->connectionName]
        ));
        $this->dispatcher->listen(TransactionRolledBack::class, fn (TransactionRolledBack $event) => $this->recordRollback(
            attributes: ['laravel.db.connection' => $event->connectionName]
        ));
    }
}

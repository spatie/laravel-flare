<?php

namespace Spatie\LaravelFlare\Recorders\LogRecorder;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Log\Events\MessageLogged;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Recorders\LogRecorder\LogMessageSpanEvent;
use Spatie\FlareClient\Recorders\LogRecorder\LogRecorder as BaseLogRecorder;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Concerns\LaravelRegisteredRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Throwable;

class LogRecorder extends BaseLogRecorder
{
    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        ?array $config = null
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public function start(): void
    {
        $this->dispatcher->listen(MessageLogged::class, fn (MessageLogged $event) => $this->recordEvent($event));
    }

    public function recordEvent(MessageLogged $event): ?LogMessageSpanEvent
    {
        if ($this->shouldIgnore($event)) {
            return null;
        }

        return $this->record(
            $event->message,
            $event->level,
            $event->context,
        );
    }

    protected function shouldIgnore(mixed $event): bool
    {
        if (! isset($event->context['exception'])) {
            return false;
        }

        if (! $event->context['exception'] instanceof Throwable) {
            return false;
        }

        return true;
    }
}

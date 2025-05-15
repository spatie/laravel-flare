<?php

namespace Spatie\LaravelFlare\Recorders\LogRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Log\Events\MessageLogged;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Recorders\LogRecorder\LogRecorder as BaseLogRecorder;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Throwable;

class LogRecorder extends BaseLogRecorder
{
    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        array $config
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public function boot(): void
    {
        $this->dispatcher->listen(MessageLogged::class, fn (MessageLogged $event) => $this->recordEvent($event));
    }

    public function recordEvent(MessageLogged $event): ?SpanEvent
    {
        if ($this->shouldIgnore($event)) {
            return null;
        }

        return $this->record(
            $event->message,
            MessageLevels::tryFrom($event->level) ?? MessageLevels::Info,
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

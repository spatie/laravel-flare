<?php

namespace Spatie\LaravelFlare\Recorders\LogRecorder;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use Spatie\FlareClient\Performance\Tracer;
use SplObjectStorage;
use Throwable;

class LogRecorder
{
    /** @var SplObjectStorage<LogMessageSpanEvent, string> */
    protected SplObjectStorage $spanEvents;

    public function __construct(
        protected Application $app,
        protected Tracer $tracer,
        protected ?int $maxLogs = 200,
        protected bool $traceLogs = false,
    ) {
        $this->spanEvents = new SplObjectStorage();
    }

    public function start(): self
    {
        $this->app['events']->listen(MessageLogged::class, [$this, 'record']);

        return $this;
    }

    public function record(MessageLogged $event): void
    {
        if ($this->shouldIgnore($event)) {
            return;
        }

        $event = LogMessageSpanEvent::fromMessageLoggedEvent($event);

        if ($this->shouldTraceLogMessage()) {
            $span = $this->tracer->currentSpan();

            $span->addEvent($event);
            $this->spanEvents->attach($event, $span->spanId);
        } else {
            $this->spanEvents->attach($event, '');
        }

        if ($this->maxLogs && count($this->spanEvents) > $this->maxLogs) {
            $this->removeOldestSpanEvent();
        }
    }

    /** @return array<array<int,string>> */
    public function getLogMessages(): array
    {
        $logMessages = [];

        foreach ($this->spanEvents as $spanEvent) {
            $logMessages[] = $spanEvent->toOriginalFlareFormat();
        }

        return $logMessages;
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

    public function reset(): void
    {
        $this->spanEvents = new SplObjectStorage();
    }

    protected function shouldTraceLogMessage(): bool
    {
        return $this->traceLogs
            && $this->tracer->isSamping()
            && $this->tracer->currentSpanId();
    }

    protected function removeOldestSpanEvent(): void
    {
        $this->spanEvents->rewind();

        if (! $this->spanEvents->valid()) {
            return;
        }

        $spanEvent = $this->spanEvents->current();
        $spanId = $this->spanEvents->getInfo();

        $this->spanEvents->detach($spanEvent);

        if (! $this->tracer->isSamping()) {
            return;
        }

        $span = $this->tracer->traces[$this->tracer->currentTraceId()][$spanId] ?? null;

        if ($span) {
            $span->events->detach($spanEvent);
        }
    }
}

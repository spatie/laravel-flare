<?php

namespace Spatie\LaravelFlare\Recorders\LogRecorder;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use SplObjectStorage;
use Throwable;

class LogRecorder implements Recorder
{
    /**  @use RecordsSpanEvents<LogMessageSpanEvent> */
    use RecordsSpanEvents;

    public function __construct(
        protected Application $app,
        protected Tracer $tracer,
        ?int $maxLogs = 200,
        bool $traceLogs = false,
    ) {
        $this->maxEntries = $maxLogs;
        $this->traceSpanEvents = $traceLogs;
    }

    public function start(): void
    {
        $this->app['events']->listen(MessageLogged::class, [$this, 'record']);
    }

    public function record(MessageLogged $event): void
    {
        if ($this->shouldIgnore($event)) {
            return;
        }

        $this->persistSpanEvent(
            LogMessageSpanEvent::fromMessageLoggedEvent($event)
        );
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

}

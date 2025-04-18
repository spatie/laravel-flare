<?php

namespace Spatie\LaravelFlare\Recorders\CommandRecorder;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Spatie\FlareClient\Recorders\CommandRecorder\CommandRecorder as BaseCommandRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;

class CommandRecorder extends BaseCommandRecorder
{
    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        array $config
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public function start(): void
    {
        $this->dispatcher->listen(CommandStarting::class, [$this, 'recordCommandStarting']);
        $this->dispatcher->listen(CommandFinished::class, [$this, 'recordCommandFinished']);
    }

    protected function canStartTraces(): bool
    {
        return true;
    }

    public function recordCommandStarting(CommandStarting $event): void
    {
        if ($this->shouldIgnoreCommand($event->command)) {
            return;
        }

        $this->recordStart(
            $event->command,
            $event->input ?? [],
            [
                'process.command_line' => str_replace("'{$event->command}'", $event->command, (string) $event->input),
            ]
        );
    }

    public function recordCommandFinished(CommandFinished $event): void
    {
        if ($this->shouldIgnoreCommand($event->command)) {
            return;
        }

        $this->recordEnd($event->exitCode);
    }

    protected function shouldIgnoreCommand(string $command): bool
    {
        return in_array($command, [
            'list',
            'queue:work',
            'horizon:work',
        ]);
    }
}

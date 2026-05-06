<?php

namespace Spatie\LaravelFlare\Recorders\CommandRecorder;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Recorders\CommandRecorder\CommandRecorder as BaseCommandRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelCommandAttributesProvider;

class CommandRecorder extends BaseCommandRecorder
{
    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        EntryPointResolver $entryPointResolver,
        protected Dispatcher $dispatcher,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $entryPointResolver, $config);
    }

    public function boot(): void
    {
        $this->dispatcher->listen(CommandStarting::class, [$this, 'recordCommandStarting']);
        $this->dispatcher->listen(CommandFinished::class, [$this, 'recordCommandFinished']);
    }

    public function recordCommandStarting(CommandStarting $event): void
    {
        if ($event->command === null) {
            return;
        }

        $this->recordStart(
            new LaravelCommandAttributesProvider($event->input, $event->command),
        );
    }

    public function recordCommandFinished(CommandFinished $event): void
    {
        $this->recordEnd($event->exitCode);
    }

    /** @return array<int, string> */
    protected function defaultIgnoredCommands(): array
    {
        return [
            'list',
            'queue:work',
            'horizon:work',
            'octane:start',
            'octane:reload',
            'vapor:work',
            'serve',
            'flare:test',
        ];
    }
}

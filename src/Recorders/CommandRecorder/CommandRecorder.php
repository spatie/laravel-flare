<?php

namespace Spatie\LaravelFlare\Recorders\CommandRecorder;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Symfony\Component\Console\Input\InputInterface;

// TODO: might be usefull to move to the Spatie\FlareClient package
class CommandRecorder implements SpansRecorder
{
    use RecordsPendingSpans;

    public static function type(): string|RecorderType
    {
        return RecorderType::Command;
    }

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        array $config
    ) {
        $this->configure($config);
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
        $this->startSpan(fn () => Span::build(
            $this->tracer->currentTraceId(),
            "Command - {$event->command}",
            parentId: $this->tracer->currentSpanId(),
            attributes: [
                'flare.span_type' => SpanType::Command,
                'process.command' => $event->command,
                'process.command_line' => str_replace("'{$event->command}'", $event->command, (string) $event->input),
                'process.command_args' => $this->getArguments($event->input),
            ]
        ));
    }

    public function recordCommandFinished(CommandFinished $event): void
    {
        $this->endSpan(function (Span $span) use ($event) {
            $span->addAttribute('process.exit_code', $event->exitCode);
        });
    }

    protected function getArguments(?InputInterface $input): array
    {
        if ($input === null) {
            return [];
        }

        $arguments = collect($input->getArguments())
            ->filter()
            ->values();

        $options = collect($input->getOptions())
            ->reject(fn (mixed $option) => $option === null || $option === false)
            ->map(fn (mixed $option, string $key) => is_bool($option) ? "--{$key}" : "--{$key}={$option}")
            ->values();

        return $arguments->merge($options)->toArray();
    }
}

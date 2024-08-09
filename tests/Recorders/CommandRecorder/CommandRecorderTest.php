<?php

use Illuminate\Contracts\Console\Kernel;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Tests\Shared\ExpectSpan;
use Spatie\FlareClient\Tests\Shared\ExpectTrace;
use Spatie\FlareClient\Tests\Shared\ExpectTracer;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Tests\Shared\IncrementingIdsGenerator;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;
use Spatie\LaravelFlare\Tests\stubs\Commands\TestCommand;
use Spatie\LaravelFlare\Tests\stubs\Exceptions\ExpectedException;

uses(ConfigureFlare::class)->beforeEach(function (){
    IncrementingIdsGenerator::setup();

    $consoleKernel = app(Kernel::class);
    $consoleKernel->addCommands([TestCommand::class]);
    $consoleKernel->rerouteSymfonyCommandEvents(); // make sure events are triggered

    test()->consoleKernel = $consoleKernel;
    test()->flare = setupFlareForTracing();
});

it('can report a command', function (){
    FakeTime::setup('2019-01-01 12:34:56');

    /** @var Flare $flare */
    $flare = test()->flare;

    test()->consoleKernel->call('flare:test-command');

    $report = $flare->report(
        new ExpectedException('This is a test exception'),
    );

    expect($report->toArray()['spans'])->toHaveCount(1);

    expect($report->toArray()['spans'][0])
        ->toHaveKey('name', 'Command - flare:test-command')
        ->toHaveKey('startTimeUnixNano', 1546346096000000000)
        ->toHaveKey('endTimeUnixNano', 1546346096000000000);

    expect($report->toArray()['spans'][0]['attributes'])
        ->toHaveCount(5)
        ->toHaveKey('flare.span_type', SpanType::Command)
        ->toHaveKey('process.command', 'flare:test-command')
        ->toHaveKey('process.command_line', 'flare:test-command')
        ->toHaveKey('process.command_args', ["flare:test-command", "with-default"])
        ->toHaveKey('process.exit_code', 0);
});

it('can trace a command', function () {
    test()->consoleKernel->call('flare:test-command');

    ExpectTracer::create(test()->flare)
        ->isWaiting()
        ->hasTraceCount(1)
        ->trace(fn (ExpectTrace $trace) => $trace
            ->hasSpanCount(1)
            ->span(fn (ExpectSpan $span) => $span
                ->hasName('Command - flare:test-command')
                ->hasType(SpanType::Command)
                ->isEnded()
                ->hasAttributeCount(5)
                ->hasAttribute('process.command', 'flare:test-command')
                ->hasAttribute('process.command_line', 'flare:test-command')
                ->hasAttribute('process.command_args', ["flare:test-command", "with-default"])
                ->hasAttribute('process.exit_code', 0)
            )
        );
});

it('can trace a command with options and arguments', function () {
    test()->consoleKernel->call('flare:test-command --option=something --boolean-option some-argument');

    ExpectTracer::create(test()->flare)
        ->isWaiting()
        ->hasTraceCount(1)
        ->trace(fn (ExpectTrace $trace) => $trace
            ->hasSpanCount(1)
            ->span(fn (ExpectSpan $span) => $span
                ->hasName('Command - flare:test-command')
                ->hasType(SpanType::Command)
                ->isEnded()
                ->hasAttributeCount(5)
                ->hasAttribute('process.command', 'flare:test-command')
                ->hasAttribute('process.command_line', 'flare:test-command --option=something --boolean-option some-argument')
                ->hasAttribute('process.command_args', ["flare:test-command", "some-argument", "--option=something", "--boolean-option"])
                ->hasAttribute('process.exit_code', 0)
            )
        );
});

it('can trace a failed command', function () {
    try {
        test()->consoleKernel->call('flare:test-command --should-fail');
    } catch (ExpectedException) {

    }

    ExpectTracer::create(test()->flare)
        ->isWaiting()
        ->hasTraceCount(1)
        ->trace(fn (ExpectTrace $trace) => $trace
            ->hasSpanCount(1)
            ->span(fn (ExpectSpan $span) => $span
                ->hasAttribute('process.exit_code', 1)
            )
        );
});

it('can trace a nested command which will be added to the same trace', function () {
    test()->consoleKernel->call('flare:test-command --run-nested');

    ExpectTracer::create(test()->flare)
        ->isWaiting()
        ->hasTraceCount(1)
        ->trace(fn (ExpectTrace $trace) => $trace
            ->hasSpanCount(2)
            ->span(
                fn (ExpectSpan $span) => $span
                    ->hasName('Command - flare:test-command')
                    ->hasType(SpanType::Command)
                    ->isEnded()
                    ->hasAttributeCount(5)
                    ->hasAttribute('process.command', 'flare:test-command')
                    ->hasAttribute('process.command_line', 'flare:test-command --run-nested')
                    ->hasAttribute('process.command_args', ["flare:test-command", "with-default", "--run-nested"])
                    ->hasAttribute('process.exit_code', 0),
                $parentSpan
            )
            ->span(fn (ExpectSpan $childSpan) => $childSpan
                ->hasName('Command - flare:test-command')
                ->hasType(SpanType::Command)
                ->isEnded()
                ->hasAttributeCount(5)
                ->hasParent($parentSpan)
                ->hasAttribute('process.command', 'flare:test-command')
                ->hasAttribute('process.command_line', 'flare:test-command --option=nested --boolean-option=1 nested-argument')
                ->hasAttribute('process.command_args', ['nested-argument', "flare:test-command", '--option=nested', '--boolean-option'])
                ->hasAttribute('process.exit_code', 0)
            )
        );
});

it('will trace multiple traces when executing multiple command after each other', function (){
    test()->consoleKernel->call('flare:test-command commandA');
    test()->consoleKernel->call('flare:test-command commandB');

    ExpectTracer::create(test()->flare)
        ->isWaiting()
        ->hasTraceCount(2)
        ->trace(fn (ExpectTrace $trace) => $trace
            ->hasSpanCount(1)
            ->span(fn (ExpectSpan $span) => $span
                ->missingParent()
                ->hasAttribute('process.command_args', ["flare:test-command", "commandA"])
            )
        )
        ->trace(fn (ExpectTrace $trace) => $trace
            ->hasSpanCount(1)
            ->span(fn (ExpectSpan $span) => $span
                ->missingParent()
                ->hasAttribute('process.command_args', ["flare:test-command", "commandB"])
            )
        );
});

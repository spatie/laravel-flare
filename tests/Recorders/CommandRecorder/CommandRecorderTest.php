<?php

use Illuminate\Contracts\Console\Kernel;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Tests\Shared\ExpectSpan;
use Spatie\FlareClient\Tests\Shared\ExpectTrace;
use Spatie\FlareClient\Tests\Shared\ExpectTracer;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\LaravelFlare\Support\TracingKernel;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;
use Spatie\LaravelFlare\Tests\stubs\Commands\TestCommand;
use Spatie\LaravelFlare\Tests\stubs\Exceptions\ExpectedException;

beforeEach(function () {
    FakeIds::setup();
    FakeTime::setup('2019-01-01 12:34:56');

    $consoleKernel = app(Kernel::class);
    $consoleKernel->addCommands([TestCommand::class]);
    $consoleKernel->rerouteSymfonyCommandEvents(); // make sure events are triggered

    test()->consoleKernel = $consoleKernel;
    test()->flare = setupFlare(alwaysSampleTraces: true);
});

it('can report a command', function () {
    /** @var Flare $flare */
    $flare = test()->flare;

    test()->consoleKernel->call('flare:test-command');

    $report = $flare->report(
        new ExpectedException('This is a test exception'),
    )->toArray();

    expect($report['events'])->toHaveCount(1);

    expect($report['events'][0])
        ->toHaveKey('startTimeUnixNano', 1546346096000000000)
        ->toHaveKey('endTimeUnixNano', 1546346096000000000)
        ->toHaveKey('type', SpanType::Command);


    expect($report['events'][0]['attributes'])
        ->toHaveCount(3)
        ->toHaveKey('process.command', 'flare:test-command')
        ->toHaveKey('process.command_args', ["flare:test-command", "with-default"])
        ->toHaveKey('process.exit_code', 0);
});

it('can trace a command', function () {
    test()->flare->tracer->startTrace();

    test()->consoleKernel->call('flare:test-command');

    test()->flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectName('Command - flare:test-command')
        ->expectType(SpanType::Command)
        ->expectMissingParentId()
        ->expectAttributesCount(5)
        ->expectAttribute('process.command', 'flare:test-command')
        ->expectAttribute('process.command_args', ["flare:test-command", "with-default"])
        ->expectAttribute('process.exit_code', 0)
        ->expectHasAttribute('flare.peak_memory_usage');
});

it('can trace a command with options and arguments', function () {
    test()->flare->tracer->startTrace();

    test()->consoleKernel->call('flare:test-command --option=something --boolean-option some-argument');

    test()->flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectName('Command - flare:test-command')
        ->expectType(SpanType::Command)
        ->expectMissingParentId()
        ->expectAttributesCount(5)
        ->expectAttribute('process.command', 'flare:test-command')
        ->expectAttribute('process.command_args', ["flare:test-command", "some-argument", "--option=something", "--boolean-option"])
        ->expectAttribute('process.exit_code', 0)
        ->expectHasAttribute('flare.peak_memory_usage');
});

it('can trace a failed command', function () {
    test()->flare->tracer->startTrace();

    try {
        test()->consoleKernel->call('flare:test-command --should-fail');
    } catch (ExpectedException) {

    }

    test()->flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpan(0)
        ->expectAttribute('process.exit_code', 1);
});

it('can trace a nested command which will be added to the same trace', function () {
    test()->flare->tracer->startTrace();

    test()->consoleKernel->call('flare:test-command --run-nested');

    test()->flare->tracer->endTrace();

    $trace = FakeApi::lastTrace()
        ->expectSpanCount(2);

    $commandSpan = $trace->expectSpan(0)
        ->expectName('Command - flare:test-command')
        ->expectType(SpanType::Command)
        ->expectMissingParentId()
        ->expectEnded()
        ->expectAttributesCount(5)
        ->expectAttribute('process.command', 'flare:test-command')
        ->expectAttribute('process.command_args', ["flare:test-command", "with-default", "--run-nested"])
        ->expectAttribute('process.exit_code', 0)
        ->expectHasAttribute('flare.peak_memory_usage');

    $trace->expectSpan(1)
        ->expectName('Command - flare:test-command')
        ->expectType(SpanType::Command)
        ->expectParentId($commandSpan)
        ->expectEnded()
        ->expectAttributesCount(5)
        ->expectAttribute('process.command', 'flare:test-command')
        ->expectAttribute('process.command_args', ['nested-argument', "flare:test-command", '--option=nested', '--boolean-option'])
        ->expectAttribute('process.exit_code', 0)
        ->expectHasAttribute('flare.peak_memory_usage');
});

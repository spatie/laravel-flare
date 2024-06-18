<?php

use Illuminate\Log\Events\MessageLogged;
use Spatie\FlareClient\Performance\Spans\Span;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\LaravelFlare\Performance\Enums\SpanEventType;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogMessageSpanEvent;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogRecorder;

it('limits the amount of recorded logs', function () {
    $recorder = new LogRecorder(app(), app(Tracer::class));

    foreach (range(1, 400) as $i) {
        $log = new MessageLogged('info', 'test '.$i, []);
        $recorder->record($log);
    }

    expect($recorder->getLogMessages())->toHaveCount(200);
    expect($recorder->getLogMessages()[0]['message'])->toBe('test 201');
});

it('limits the amount of recorded logs when tracing', function () {
    $recorder = new LogRecorder(app(), $tracer = app(Tracer::class), traceLogs: true);

    $tracer->startTrace();
    $tracer->addSpan(Span::build($tracer->currentTraceId(), 'Parent Span'), makeCurrent: true);

    foreach (range(1, 400) as $i) {
        $log = new MessageLogged('info', 'test '.$i, []);
        $recorder->record($log);
    }

    expect($recorder->getLogMessages())->toHaveCount(200);
    expect($recorder->getLogMessages()[0]['message'])->toBe('test 201');
});

it('does not limit the amount of recorded queries', function () {
    $recorder = new LogRecorder(app(), app(Tracer::class), maxLogs: null);

    foreach (range(1, 400) as $i) {
        $log = new MessageLogged('info', 'test '.$i, []);
        $recorder->record($log);
    }

    expect($recorder->getLogMessages())->toHaveCount(400);
    expect($recorder->getLogMessages()[0]['message'])->toBe('test 1');
});

it('does not record log containing an exception', function () {
    $recorder = new LogRecorder(app(), app(Tracer::class), maxLogs: null);

    $log = new MessageLogged('info', 'test 1', ['exception' => new Exception('test')]);
    $recorder->record($log);
    $log = new MessageLogged('info', 'test 2', []);
    $recorder->record($log);

    expect($recorder->getLogMessages())->toHaveCount(1);
    expect($recorder->getLogMessages()[0]['message'])->toBe('test 2');
});

it('does not ignore log if exception key does not contain exception', function () {
    $recorder = new LogRecorder(app(), app(Tracer::class), maxLogs: null);

    $log = new MessageLogged('info', 'test 1', ['exception' => 'test']);
    $recorder->record($log);
    $log = new MessageLogged('info', 'test 2', []);
    $recorder->record($log);

    expect($recorder->getLogMessages())->toHaveCount(2);
    expect($recorder->getLogMessages()[0]['message'])->toBe('test 1');
    expect($recorder->getLogMessages()[1]['message'])->toBe('test 2');
    expect($recorder->getLogMessages()[0]['context'])->toBeArray();
    $this->assertArrayHasKey('exception', $recorder->getLogMessages()[0]['context']);
    expect($recorder->getLogMessages()[0]['context']['exception'])->toBe('test');
});

it('can trace a log message', function () {
    $recorder = new LogRecorder(app(), $tracer = app(Tracer::class), traceLogs: true);

    $tracer->startTrace();
    $tracer->addSpan($span = Span::build($tracer->currentTraceId(), 'Parent Span'), makeCurrent: true);

    $log = new MessageLogged('info', 'test', ['some' => 'context']);
    $recorder->record($log);

    expect($span->events)->toHaveCount(1);

    $event = $span->events->current();

    expect($event)
        ->toBeInstanceOf(LogMessageSpanEvent::class)
        ->name->toBe('Log entry')
        ->timeUs->toBeInt()->toBeDigits(16);

    expect($event->attributes)
        ->toBeArray()
        ->toHaveCount(4)
        ->toHaveKey('log.level', 'info')
        ->toHaveKey('log.message', 'test')
        ->toHaveKey('log.context', ['some' => 'context'])
        ->toHaveKey('flare.span_event_type', SpanEventType::Log);
});

it('can disable tracing log messages', function (){
    $recorder = new LogRecorder(app(), $tracer = app(Tracer::class), traceLogs: false);

    $tracer->startTrace();
    $tracer->addSpan($span = Span::build($tracer->currentTraceId(), 'Parent Span'), makeCurrent: true);

    $log = new MessageLogged('info', 'test', ['some' => 'context']);
    $recorder->record($log);

    expect($span->events)->toHaveCount(0);
});

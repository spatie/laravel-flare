<?php

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Performance\Spans\Span;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\FlareClient\Tests\Shared\ExpectSpan;
use Spatie\FlareClient\Tests\Shared\ExpectSpanEvent;
use Spatie\FlareClient\Tests\Shared\ExpectTrace;
use Spatie\FlareClient\Tests\Shared\ExpectTracer;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogMessageSpanEvent;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogRecorder;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;

uses(ConfigureFlare::class);

it('traces logs', function () {
    $flare = setupFlareForTracing();

    $flare->tracer->startTrace();
    $flare->tracer->startSpan('Parent Span');

    Log::info('Hello world', [
        'some' => 'context',
    ]);

    ExpectTracer::create($flare)
        ->hasTraceCount(1)
        ->isSampling()
        ->trace(fn (ExpectTrace $trace) => $trace
            ->hasSpanCount(1)
            ->span(fn (ExpectSpan $span) => $span
                ->hasSpanEventCount(1)
                ->spanEvent(fn(ExpectSpanEvent $spanEvent) => $spanEvent
                    ->hasName('Log entry')
                    ->hasType(SpanEventType::Log)
                    ->hasAttributeCount(4)
                    ->hasAttribute('log.level', 'info')
                    ->hasAttribute('log.message', 'Hello world')
                    ->hasAttribute('log.context', ['some' => 'context'])
                )
            ));
});

it('can report logs', function (){
    $flare = setupFlare();

    Log::info('Hello world', [
        'some' => 'context',
    ]);

    $report = $flare->report(new Exception('Report this'));

    expect($report->toArray()['span_events'])->toHaveCount(1);

    expect($report->toArray()['span_events'][0])
        ->toHaveKey('name', 'Log entry')
        ->toHaveKey('attributes', [
            'flare.span_event_type'=> SpanEventType::Log,
            'log.level' => 'info',
            'log.message' => 'Hello world',
            'log.context' => ['some' => 'context'],
        ]);
});

it('will not record logs containing exceptions', function (){
    $flare = setupFlare();

    Log::info('Hello world', [
        'exception' => new Exception('This is an exception'),
    ]);

    $report = $flare->report(new Exception('Report this'));

    expect($report->toArray()['span_events'])->toHaveCount(0);
});


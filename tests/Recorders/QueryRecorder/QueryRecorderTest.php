<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Arr;
use Spatie\FlareClient\Performance\Spans\Span;
use Spatie\FlareClient\Performance\Support\TraceId;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QuerySpan;

it('limits the amount of recorded queries', function () {
    $recorder = new QueryRecorder(app(), app(Tracer::class), reportBindings: true, maxQueries: 200, traceQueryOriginThreshold: 300);
    $connection = app(Connection::class);

    foreach (range(1, 400) as $i) {
        $query = new QueryExecuted('query '.$i, [], 300, $connection);
        $recorder->record($query);
    }
    expect($recorder->getQueries())->toHaveCount(200);
    expect($recorder->getQueries()[0]['sql'])->toBe('query 201');
});

it('does not limit the amount of recorded queries', function () {
    $recorder = new QueryRecorder(app(), app(Tracer::class), reportBindings: true, maxQueries: null, traceQueryOriginThreshold: 300);
    $connection = app(Connection::class);

    foreach (range(1, 400) as $i) {
        $query = new QueryExecuted('query '.$i, [], 300, $connection);
        $recorder->record($query);
    }

    expect($recorder->getQueries())->toHaveCount(400);
    expect($recorder->getQueries()[0]['sql'])->toBe('query 1');
});

it('records bindings', function () {
    $recorder = new QueryRecorder(app(), app(Tracer::class), reportBindings: true, maxQueries: 200, traceQueryOriginThreshold: 300);
    $connection = app(Connection::class);

    $query = new QueryExecuted('query 1', ['abc' => 123], 300, $connection);
    $recorder->record($query);

    expect($recorder->getQueries())->toHaveCount(1);
    expect($recorder->getQueries()[0]['sql'])->toBe('query 1');
    expect($recorder->getQueries()[0]['bindings'])->toBeArray();
    expect($recorder->getQueries()[0]['sql'])->toBe('query 1');
    expect($recorder->getQueries()[0]['bindings']['abc'])->toBe(123);
});

it('does not record bindings', function () {
    $recorder = new QueryRecorder(app(), app(Tracer::class), reportBindings: false, maxQueries: 200, traceQueryOriginThreshold: 300);
    $connection = app(Connection::class);

    $query = new QueryExecuted('query 1', ['abc' => 123], 300, $connection);
    $recorder->record($query);

    expect($recorder->getQueries())->toHaveCount(1);
    expect($recorder->getQueries()[0]['sql'])->toBe('query 1');
    expect($recorder->getQueries()[0]['bindings'])->toBeNull();
});

it('records origins when tracing and a threshold is met', function () {
    $tracer = app(Tracer::class);

    $tracer->startTrace();
    $tracer->addSpan(Span::build($tracer->currentTraceId(), 'Parent Span'), makeCurrent: true);

    $recorder = new QueryRecorder(app(), $tracer, reportBindings: true, maxQueries: 200, traceQueryOriginThreshold: 300);
    $connection = app(Connection::class);

    $query = new QueryExecuted('query 1', ['abc' => 123], 300, $connection);

    $recorder->record($query);

    $querySpan = Arr::first(Arr::except($tracer->traces[$tracer->currentTraceId()], $tracer->currentSpanId()));

    expect($querySpan)
        ->toBeInstanceOf(QuerySpan::class)
        ->traceId->toBe($tracer->currentTraceId())
        ->spanId->toBeString()
        ->parentSpanId->toBe($tracer->currentSpanId())
        ->startUs->toBeInt()->toBeDigits(16)
        ->endUs->toBeInt()->toBeDigits(16)
        ->name->toBe('Query - query 1');

    expect($querySpan->attributes)
        ->toBeArray()
        ->toHaveCount(9)
        ->toHaveKey('flare.span_type', SpanType::Query)
        ->toHaveKey('db.system', 'sqlite')
        ->toHaveKey('db.name', ':memory:')
        ->toHaveKey('db.statement', 'query 1')
        ->toHaveKey('laravel.db.connection', 'testing')
        ->toHaveKey('db.sql.bindings', ['abc' => 123])
        ->toHaveKey('code.filepath')
        ->toHaveKey('code.lineno')
        ->toHaveKey('code.function');
});

it('will not record origins or add span info when not tracing', function () {
    $tracer = app(Tracer::class);

    $recorder = new QueryRecorder(app(), $tracer, reportBindings: true, maxQueries: 200, traceQueryOriginThreshold: 300);
    $connection = app(Connection::class);

    $query = new QueryExecuted('query 1', ['abc' => 123], 300, $connection);

    $recorder->record($query);

    $querySpan = Arr::first($recorder->getSpans());

    expect($querySpan)
        ->toBeInstanceOf(QuerySpan::class)
        ->traceId->toBe('')
        ->spanId->toBeString()
        ->parentSpanId->toBe('')
        ->startUs->toBeInt()->toBeDigits(16)
        ->endUs->toBeInt()->toBeDigits(16)
        ->name->toBe('Query - query 1');

    expect($querySpan->attributes)
        ->toBeArray()
        ->toHaveCount(6)
        ->not()->toHaveKey('code.filepath')
        ->not()->toHaveKey('code.lineno')
        ->not()->toHaveKey('code.function');
});

it('will not record origins when a threshold is not met', function () {
    $tracer = app(Tracer::class);

    $tracer->startTrace();
    $tracer->addSpan(Span::build($tracer->currentTraceId(), 'Parent Span'), makeCurrent: true);

    $recorder = new QueryRecorder(app(), $tracer, reportBindings: true, maxQueries: 200, traceQueryOriginThreshold: 300);
    $connection = app(Connection::class);

    $query = new QueryExecuted('query 1', ['abc' => 123], 100, $connection);

    $recorder->record($query);

    $querySpan = Arr::first(Arr::except($tracer->traces[$tracer->currentTraceId()], $tracer->currentSpanId()));

    expect($querySpan->attributes)
        ->toBeArray()
        ->toHaveCount(6)
        ->not()->toHaveKey('code.filepath')
        ->not()->toHaveKey('code.lineno')
        ->not()->toHaveKey('code.function');
});

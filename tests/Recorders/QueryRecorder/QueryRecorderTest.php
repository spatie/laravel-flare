<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tests\Shared\ExpectSpan;
use Spatie\FlareClient\Tests\Shared\ExpectTrace;
use Spatie\FlareClient\Tests\Shared\ExpectTracer;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;

uses(ConfigureFlare::class);

beforeEach(function () {
    Schema::dropIfExists('users');

    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

it('traces queries', function () {
    $flare = setupFlareForTracing();

    $flare->tracer->startTrace();

    DB::select('SELECT * FROM users WHERE id = ?', [42]);

    ExpectTracer::create($flare)
        ->hasTraceCount(1)
        ->isSampling()
        ->trace(fn (ExpectTrace $trace) => $trace
            ->hasSpanCount(1)
            ->span(
                fn (ExpectSpan $span) => $span
                ->isEnded()
                ->hasName('Query - SELECT * FROM users WHERE id = ?')
                ->hasType(SpanType::Query)
                ->hasAttribute('db.system', 'sqlite')
                ->hasAttribute('db.name', ':memory:')
                ->hasAttribute('db.statement', 'SELECT * FROM users WHERE id = ?')
                ->hasAttribute('laravel.db.connection', 'testing')
                ->hasAttribute('db.sql.bindings', ['42'])
            ));
});

it('can report queries', function () {
    $flare = setupFlare();

    DB::select('SELECT * FROM users WHERE id = ?', [42]);

    $report = $flare->report(new Exception('Report this'));

    expect($report->toArray()['events'])->toHaveCount(1);

    expect($report->toArray()['events'][0])
        ->toHaveKey('type', SpanType::Query)
        ->toHaveKey('attributes', [
            'db.system' => 'sqlite',
            'db.name' => ':memory:',
            'db.statement' => 'SELECT * FROM users WHERE id = ?',
            'laravel.db.connection' => 'testing',
            'db.sql.bindings' => ['42'],
        ]);
});

it('can stop recording bindings', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->addQueries(includeBindings: false));

    DB::select('SELECT * FROM users WHERE id = ?', [42]);

    $report = $flare->report(new Exception('Report this'));

    expect($report->toArray()['events'][0]['attributes'])->not()->toHaveKey('db.sql.bindings');
});

it('will add origin attributes when a threshold is met and tracing', function () {
    $flare = setupFlareForTracing(fn (FlareConfig $config) => $config->addQueries(findOriginThreshold: TimeHelper::milliseconds(300)));

    $flare->tracer->startTrace();

    $flare->query()->record('SELECT * FROM users', duration: TimeHelper::milliseconds(400));

    $report = $flare->report(new Exception('Report this'));

    expect($report->toArray()['events'][0]['attributes'])->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('will not add origin attributes when a threshold is met and only reporting', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->addQueries(findOriginThreshold: TimeHelper::milliseconds(300)));

    $flare->query()->record('SELECT * FROM users', duration: TimeHelper::milliseconds(400));

    $report = $flare->report(new Exception('Report this'));

    expect($report->toArray()['events'][0]['attributes'])->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('will not add origin attributes when a threshold is not met', function () {
    $flare = setupFlareForTracing(fn (FlareConfig $config) => $config->addQueries(findOriginThreshold: TimeHelper::milliseconds(300)));

    $flare->tracer->startTrace();

    $flare->query()->record('SELECT * FROM users', duration: TimeHelper::milliseconds(200));

    $report = $flare->report(new Exception('Report this'));

    expect($report->toArray()['events'][0]['attributes'])->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('will not add origin attributes when the trace origin feature is disabled', function () {
    $flare = setupFlareForTracing(fn (FlareConfig $config) => $config->addQueries(findOrigin: false, findOriginThreshold: TimeHelper::milliseconds(300)));

    $flare->tracer->startTrace();

    $flare->query()->record('SELECT * FROM users', duration: TimeHelper::milliseconds(400));

    $report = $flare->report(new Exception('Report this'));

    expect($report->toArray()['events'][0]['attributes'])->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});


it('will always add origin attributes when no threshold is set', function () {
    $flare = setupFlareForTracing(fn (FlareConfig $config) => $config->addQueries(findOriginThreshold: null));

    $flare->tracer->startTrace();

    $flare->query()->record('SELECT * FROM users', duration: TimeHelper::milliseconds(200));

    $report = $flare->report(new Exception('Report this'));

    expect($report->toArray()['events'][0]['attributes'])->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

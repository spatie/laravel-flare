<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tests\Shared\ExpectSpan;
use Spatie\FlareClient\Tests\Shared\ExpectTrace;
use Spatie\FlareClient\Tests\Shared\ExpectTracer;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;

beforeEach(function () {
    Schema::dropIfExists('users');

    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

it('traces queries', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    DB::select('SELECT * FROM users WHERE id = ?', [42]);

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectName('Query - SELECT * FROM users WHERE id = ?')
        ->expectEnded()
        ->expectType(SpanType::Query)
        ->expectAttribute('db.system', 'sqlite')
        ->expectAttribute('db.name', ':memory:')
        ->expectAttribute('db.statement', 'SELECT * FROM users WHERE id = ?')
        ->expectAttribute('laravel.db.connection', 'testing')
        ->expectAttribute('db.sql.bindings', [42]);
});

it('can report queries', function () {
    $flare = setupFlare();

    DB::select('SELECT * FROM users WHERE id = ?', [42]);

    $report = $flare->report(new Exception('Report this'))->toArray();

    expect($report['events'])->toHaveCount(1);

    expect($report['events'][0])
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
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectQueries(includeBindings: false));

    DB::select('SELECT * FROM users WHERE id = ?', [42]);

    $report = $flare->report(new Exception('Report this'))->toArray();

    expect($report['events'][0]['attributes'])->not()->toHaveKey('db.sql.bindings');
});

it('will add origin attributes when a threshold is met and tracing', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectQueries(findOriginThreshold: TimeHelper::milliseconds(300)), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $flare->query()->record('SELECT * FROM users', duration: TimeHelper::milliseconds(400));

    $report = $flare->report(new Exception('Report this'))->toArray();

    expect($report['events'][0]['attributes'])->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('will not add origin attributes when a threshold is met and only reporting', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectQueries(findOriginThreshold: TimeHelper::milliseconds(300)));

    $flare->query()->record('SELECT * FROM users', duration: TimeHelper::milliseconds(400));

    $report = $flare->report(new Exception('Report this'))->toArray();

    expect($report['events'][0]['attributes'])->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('will not add origin attributes when a threshold is not met', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectQueries(findOriginThreshold: TimeHelper::milliseconds(300)), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $flare->query()->record('SELECT * FROM users', duration: TimeHelper::milliseconds(200));

    $report = $flare->report(new Exception('Report this'))->toArray();

    expect($report['events'][0]['attributes'])->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('will not add origin attributes when the trace origin feature is disabled', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectQueries(findOrigin: false, findOriginThreshold: TimeHelper::milliseconds(300)), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $flare->query()->record('SELECT * FROM users', duration: TimeHelper::milliseconds(400));

    $report = $flare->report(new Exception('Report this'))->toArray();

    expect($report['events'][0]['attributes'])->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});


it('will always add origin attributes when no threshold is set', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectQueries(findOriginThreshold: null), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $flare->query()->record('SELECT * FROM users', duration: TimeHelper::milliseconds(200));

    $report = $flare->report(new Exception('Report this'))->toArray();

    expect($report['events'][0]['attributes'])->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

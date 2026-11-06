<?php

use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tests\Shared\FakeApi;

beforeEach(function () {
    config()->set('database.default', 'testing');
    config()->set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
});

it('traces lazy database connections once', function () {
    $flare = setupFlare(alwaysSampleTraces: true);
    $flare->tracer->startTrace();
    $parent = $flare->tracer->startSpan('Operation');

    $connection = DB::connection();

    expect($connection->getRawPdo())->toBeInstanceOf(Closure::class);

    DB::select('SELECT 42');
    DB::select('SELECT 17');

    expect($connection->getRawPdo())->toBeInstanceOf(PDO::class);

    $flare->tracer->endSpan($parent);
    $flare->tracer->endTrace();

    $trace = FakeApi::lastTrace()->expectSpanCount(4)->expectAllSpansClosed();

    $trace->expectSpanCount(1, SpanType::DatabaseConnection)
        ->expectSpan(SpanType::DatabaseConnection)
        ->expectName('Database connection')
        ->expectParentId($parent)
        ->expectAttribute('db.system', 'sqlite')
        ->expectAttribute('db.name', ':memory:')
        ->expectAttribute('laravel.db.connection', 'testing')
        ->expectAttribute('laravel.db.connection_role', 'write');
});

it('closes failed connection attempts without recording the exception', function () {
    $flare = setupFlare(alwaysSampleTraces: true);
    $exception = new PDOException('secret connection credentials');

    DB::extend('sqlite', fn ($config) => new SQLiteConnection(
        fn () => throw $exception,
        ':memory:',
        '',
        $config,
    ));

    $flare->tracer->startTrace();

    try {
        DB::connection()->getPdo();
        $this->fail('Expected connection failure');
    } catch (PDOException $caught) {
        expect($caught)->toBe($exception);
    }

    $flare->tracer->endTrace();

    $trace = FakeApi::lastTrace()->expectSpanCount(1)->expectAllSpansClosed();

    $span = $trace->expectSpan(SpanType::DatabaseConnection);

    expect($span->span['status']['code'])->toBe(SpanStatusCode::Error);
    expect(json_encode($trace->trace))->not->toContain('secret connection credentials');
});

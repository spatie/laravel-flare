<?php

use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\LaravelFlare\Enums\SpanType as LaravelSpanType;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\Recorders\DatabaseConnectionRecorder\DatabaseConnectionRecorder;

beforeEach(function () {
    config()->set('database.default', 'testing');
    config()->set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
});

it('traces lazy connections once without changing inclusive query timings', function () {
    FakeTime::setup('2019-01-01 12:34:56');
    $this->beforeApplicationDestroyed(fn () => FakeTime::reset());
    $flare = setupFlare(alwaysSampleTraces: true);
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        $queries[] = $event;
    });
    $flare->tracer->startTrace();
    $parent = $flare->tracer->startSpan('Operation');

    $connection = DB::connection();
    expect($connection->getRawPdo())->toBeInstanceOf(Closure::class);
    expect(app(DatabaseConnectionRecorder::class)->getSpans())->toBeEmpty();

    expect(DB::selectOne('SELECT 42 AS answer')->answer)->toBe(42);
    $pdo = $connection->getRawPdo();
    DB::select('SELECT 17');

    expect($connection->getRawPdo())->toBe($pdo)->toBeInstanceOf(PDO::class);
    $flare->tracer->endSpan($parent);
    $flare->tracer->endTrace();

    $trace = FakeApi::lastTrace()->expectSpanCount(4)->expectAllSpansClosed();
    $connect = $trace->expectSpan(LaravelSpanType::DatabaseConnection)
        ->expectName('Database connection')
        ->expectAttribute('db.system', 'sqlite')
        ->expectAttribute('laravel.db.connection', 'testing')
        ->expectAttribute('laravel.db.connection.role', 'write');
    $query = $trace->expectSpan(2)->expectType(SpanType::Query)->expectParentId($parent);

    expect($query->span['parentSpanId'])->toBe($connect->span['parentSpanId']);
    expect((int) $query->span['endTimeUnixNano'] - (int) $query->span['startTimeUnixNano'])
        ->toBe(TimeHelper::milliseconds($queries[0]->time));
});

it('closes failed connection attempts and preserves the original exception', function () {
    $flare = setupFlare(alwaysSampleTraces: true);
    $exception = new PDOException('secret connection credentials');
    DB::extend('sqlite', fn ($config) => new SQLiteConnection(
        fn () => throw $exception,
        ':memory:',
        '',
        $config,
    ));
    $flare->tracer->startTrace();
    $parent = $flare->tracer->startSpan('Operation');

    foreach ([1, 2] as $attempt) {
        try {
            DB::connection()->getPdo();
            $this->fail('Expected connection failure');
        } catch (PDOException $caught) {
            expect($caught)->toBe($exception);
        }
    }

    $flare->tracer->endSpan($parent);
    $flare->tracer->endTrace();

    $trace = FakeApi::lastTrace()->expectSpanCount(3)->expectAllSpansClosed();
    expect($trace->expectSpan(1)->span['status']['code'])->toBe(SpanStatusCode::Error);
    $trace->expectSpan(1)->expectType(LaravelSpanType::DatabaseConnection)->expectParentId($parent);
    $trace->expectSpan(2)->expectType(LaravelSpanType::DatabaseConnection)->expectParentId($parent);
    expect(json_encode($trace->trace))->not->toContain('secret connection credentials');
});

it('traces separate read and write connections without resetting active transactions', function () {
    $flare = setupFlare(alwaysSampleTraces: true);
    config()->set('database.connections.testing.read', ['database' => ':memory:']);
    config()->set('database.connections.testing.write', ['database' => ':memory:']);
    $flare->tracer->startTrace();

    $connection = DB::connection();
    $read = $connection->getReadPdo();
    $connection->beginTransaction();
    event(new ConnectionEstablished($connection));

    expect($connection->transactionLevel())->toBe(1);
    expect($connection->getReadPdo())->toBe($connection->getPdo())->not->toBe($read);
    $connection->rollBack();
    expect($connection->getReadPdo())->toBe($read);
    $flare->tracer->endTrace();

    $trace = FakeApi::lastTrace()->expectSpanCount(2, LaravelSpanType::DatabaseConnection)->expectAllSpansClosed();
    $trace->expectSpan(0)->expectAttribute('laravel.db.connection.role', 'read');
    $trace->expectSpan(1)->expectAttribute('laravel.db.connection.role', 'write');
});

it('traces lazy direct connections without opening the write connection', function () {
    $flare = setupFlare(alwaysSampleTraces: true);
    app()->bind('db.connector.pgsql', SQLiteConnector::class);
    config()->set('database.connections.testing.driver', 'pgsql');
    config()->set('database.connections.testing.direct', ['database' => ':memory:']);
    $flare->tracer->startTrace();

    $connection = DB::connection();
    expect($connection->getRawDirectPdo())->toBeInstanceOf(Closure::class);
    expect(app(DatabaseConnectionRecorder::class)->getSpans())->toBeEmpty();

    $pdo = $connection->getDirectPdo();
    expect($connection->getDirectPdo())->toBe($pdo)->toBeInstanceOf(PDO::class);
    expect($connection->getRawPdo())->toBeInstanceOf(Closure::class);
    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectAllSpansClosed()
        ->expectSpan(LaravelSpanType::DatabaseConnection)
        ->expectAttribute('laravel.db.connection.role', 'direct');
})->skip(! method_exists(SQLiteConnection::class, 'getRawDirectPdo'), 'Laravel does not support direct connections');

it('traces lazy reconnections when Laravel emits connection events', function () {
    DB::connection()->getPdo();
    $flare = setupFlare(alwaysSampleTraces: true);
    $events = 0;
    app('events')->listen(ConnectionEstablished::class, function () use (&$events) {
        $events++;
    });
    $flare->tracer->startTrace();

    DB::reconnect();
    expect(DB::connection()->getRawPdo())->toBeInstanceOf(Closure::class);
    expect(app(DatabaseConnectionRecorder::class)->getSpans())->toBeEmpty();
    DB::connection()->getPdo();
    $flare->tracer->endTrace();

    if ($events === 0) {
        FakeApi::assertNothingSent();

        return;
    }

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectAllSpansClosed()
        ->expectSpan(LaravelSpanType::DatabaseConnection)
        ->expectName('Database connection');
});

it('respects query collection settings for connections', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectQueries(withTraces: false, withErrors: false), alwaysSampleTraces: true);
    $flare->tracer->startTrace();

    expect(DB::selectOne('SELECT 42 AS answer')->answer)->toBe(42);

    expect($flare->report(new Exception('Report this'))->toArray()['events'])->toBeEmpty();
    FakeApi::reset();
    $flare->tracer->endTrace();
    FakeApi::assertNothingSent();
});

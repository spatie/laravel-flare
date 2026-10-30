<?php

use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\LaravelFlare\FlareConfig;

beforeEach(function () {
    config()->set('database.default', 'testing');
    config()->set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

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
        ->expectAttribute('db.name', DB::connection()->getDatabaseName())
        ->expectAttribute('db.statement', 'SELECT * FROM users WHERE id = ?')
        ->expectAttribute('laravel.db.connection', DB::connection()->getName())
        ->expectAttribute('db.sql.bindings', [42]);
});

it('traces lazy connections once without changing inclusive query timings', function () {
    $flare = setupFlare(alwaysSampleTraces: true);
    DB::purge();
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        $queries[] = $event;
    });
    $flare->tracer->startTrace();
    $parent = $flare->tracer->startSpan('Operation');

    $connection = DB::connection();
    expect($connection->getRawPdo())->toBeInstanceOf(Closure::class);
    expect($flare->query()->getSpans())->toBeEmpty();

    expect(DB::selectOne('SELECT 42 AS answer')->answer)->toBe(42);
    $pdo = $connection->getRawPdo();
    DB::select('SELECT 17');

    expect($connection->getRawPdo())->toBe($pdo)->toBeInstanceOf(PDO::class);
    $flare->tracer->endSpan($parent);
    $flare->tracer->endTrace();

    $trace = FakeApi::lastTrace()->expectSpanCount(4)->expectAllSpansClosed();
    $connect = $trace->expectSpan(1)
        ->expectName('Database connection')
        ->expectType(SpanType::Custom)
        ->expectAttribute('db.system', 'sqlite')
        ->expectAttribute('laravel.db.connection', 'testing')
        ->expectAttribute('laravel.db.connection.role', 'write');
    $query = $trace->expectSpan(2)->expectType(SpanType::Query)->expectParentId($parent);

    expect($query->span['parentSpanId'])->toBe($connect->span['parentSpanId']);
    expect((int) $query->span['endTimeUnixNano'] - (int) $query->span['startTimeUnixNano'])
        ->toBe(TimeHelper::milliseconds($queries[0]->time));
})->skip(! class_exists(ConnectionEstablished::class), 'Laravel does not emit connection events');

it('closes failed connection attempts and preserves the original exception', function () {
    $flare = setupFlare(alwaysSampleTraces: true);
    $exception = new PDOException('secret connection credentials');
    DB::purge();
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
    $trace->expectSpan(1)->expectParentId($parent);
    $trace->expectSpan(2)->expectParentId($parent);
    expect(json_encode($trace->trace))->not->toContain('secret connection credentials');
})->skip(! class_exists(ConnectionEstablished::class), 'Laravel does not emit connection events');

it('traces separate read and write connections without resetting active transactions', function () {
    $flare = setupFlare(alwaysSampleTraces: true);
    DB::purge();
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

    $trace = FakeApi::lastTrace()->expectSpanCount(2, SpanType::Custom)->expectAllSpansClosed();
    $trace->expectSpan(0)->expectAttribute('laravel.db.connection.role', 'read');
    $trace->expectSpan(1)->expectAttribute('laravel.db.connection.role', 'write');
})->skip(! class_exists(ConnectionEstablished::class), 'Laravel does not emit connection events');

it('traces lazy direct connections without opening the write connection', function () {
    $flare = setupFlare(alwaysSampleTraces: true);
    DB::purge();
    app()->bind('db.connector.pgsql', SQLiteConnector::class);
    config()->set('database.connections.testing.driver', 'pgsql');
    config()->set('database.connections.testing.direct', ['database' => ':memory:']);
    $flare->tracer->startTrace();

    $connection = DB::connection();
    expect($connection->getRawDirectPdo())->toBeInstanceOf(Closure::class);
    expect($flare->query()->getSpans())->toBeEmpty();

    $pdo = $connection->getDirectPdo();
    expect($connection->getDirectPdo())->toBe($pdo)->toBeInstanceOf(PDO::class);
    expect($connection->getRawPdo())->toBeInstanceOf(Closure::class);
    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectAllSpansClosed()
        ->expectSpan(0)
        ->expectAttribute('laravel.db.connection.role', 'direct');
})->skip(! method_exists(SQLiteConnection::class, 'getRawDirectPdo'), 'Laravel does not support direct connections');

it('traces lazy reconnections when Laravel emits connection events', function () {
    $flare = setupFlare(alwaysSampleTraces: true);
    $events = 0;
    app('events')->listen(ConnectionEstablished::class, function () use (&$events) {
        $events++;
    });
    $flare->tracer->startTrace();

    DB::reconnect();
    expect(DB::connection()->getRawPdo())->toBeInstanceOf(Closure::class);
    expect($flare->query()->getSpans())->toBeEmpty();
    DB::connection()->getPdo();
    $flare->tracer->endTrace();

    if ($events === 0) {
        FakeApi::assertNothingSent();

        return;
    }

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectAllSpansClosed()
        ->expectSpan(0)
        ->expectName('Database connection');
});

it('can report queries', function () {
    $flare = setupFlare();

    $flare->tracer->startTrace();

    DB::select('SELECT * FROM users WHERE id = ?', [42]);

    $report = $flare->report(new Exception('Report this'))->toArray();

    expect($report['events'])->toHaveCount(1);

    expect($report['events'][0])
        ->toHaveKey('type', SpanType::Query)
        ->toHaveKey('attributes', [
            'db.system' => 'sqlite',
            'db.name' => DB::connection()->getDatabaseName(),
            'db.statement' => 'SELECT * FROM users WHERE id = ?',
            'laravel.db.connection' => DB::connection()->getName(),
            'db.sql.bindings' => ['42'],
        ]);
});

it('can stop recording bindings', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectQueries(includeBindings: false));

    $flare->tracer->startTrace();

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

    $flare->tracer->startTrace();

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

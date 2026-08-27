<?php

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use function Pest\Laravel\get;
use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\Enums\CacheResult;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\LaravelFlare\FlareConfig;

beforeEach(function () {
    config()->set('cache.default', 'array');
});

it('records cache operations', function (
    Closure $preRecording,
    Closure $record,
    Closure $assert,
) {
    Cache::clear();

    $preRecording();

    setupFlare();

    Route::get('exception', function () use ($record) {
        $record();

        throw new Exception('This is a failed operation');
    });

    get('exception')->assertStatus(500);

    $spanEvents = FakeApi::lastReport()->toArray()['events'];

    $cacheSpanEvents = array_values(array_filter(
        $spanEvents,
        fn (array $event) => $event['type'] === SpanEventType::Cache,
    )); // Remove all logs from other packages

    expect($cacheSpanEvents)->toHaveCount(1);

    $assert($cacheSpanEvents[0]);
})->with('cache recorder');

it('ignores framework and vendor cache keys', function () {
    Cache::clear();

    setupFlare();

    Route::get('exception', function () {
        cache()->get('illuminate:queue:restart');

        throw new Exception('This is a failed operation');
    });

    get('exception')->assertStatus(500);

    $cacheSpanEvents = array_values(array_filter(
        FakeApi::lastReport()->toArray()['events'],
        fn (array $event) => $event['type'] === SpanEventType::Cache,
    ));

    expect($cacheSpanEvents)->toHaveCount(0);
});

it('does not ignore keys written by flexible since they contain a user defined key', function () {
    Cache::clear();

    setupFlare();

    Route::get('exception', function () {
        Cache::flexible('some_key', [5, 10], fn () => 'some_value');

        throw new Exception('This is a failed operation');
    });

    get('exception')->assertStatus(500);

    $cacheSpanEvents = array_values(array_filter(
        FakeApi::lastReport()->toArray()['events'],
        fn (array $event) => $event['type'] === SpanEventType::Cache,
    ));

    expect(array_column(array_column($cacheSpanEvents, 'attributes'), 'cache.key'))
        ->toContain('illuminate:cache:flexible:created:some_key');
})->skip(
    fn () => ! method_exists(Repository::class, 'flexible'),
    'Cache::flexible() requires Laravel 11.23 or higher',
);

it('can ignore additional cache keys', function () {
    Cache::clear();

    setupFlare(fn (FlareConfig $config) => $config->collectCacheEvents(
        ignoredKeys: ['/^internal:/'],
    ));

    Route::get('exception', function () {
        cache()->get('internal:some_key');
        cache()->get('some_key');

        throw new Exception('This is a failed operation');
    });

    get('exception')->assertStatus(500);

    $cacheSpanEvents = array_values(array_filter(
        FakeApi::lastReport()->toArray()['events'],
        fn (array $event) => $event['type'] === SpanEventType::Cache,
    ));

    expect($cacheSpanEvents)->toHaveCount(1);
    expect($cacheSpanEvents[0]['attributes']['cache.key'])->toBe('some_key');
});

dataset('cache recorder', function () {
    yield 'cache hit' => [
        fn () => cache()->put('some_key', 'some_value', 60),
        fn () => cache()->get('some_key'),
        function (array $event) {
            expect($event['type'])->toBe(SpanEventType::Cache);
            expect($event['attributes']['cache.key'])->toBe('some_key');
            expect($event['attributes']['cache.store'])->toBe(version_compare(app()->version(), '11.0', '<') ? null : config('cache.default'));
            expect($event['attributes']['cache.operation'])->toBe(CacheOperation::Get);
            expect($event['attributes']['cache.result'])->toBe(CacheResult::Hit);
        },
    ];

    yield 'cache miss' => [
        fn () => null,
        fn () => cache()->get('some_key'),
        function (array $event) {
            expect($event['type'])->toBe(SpanEventType::Cache);
            expect($event['attributes']['cache.key'])->toBe('some_key');
            expect($event['attributes']['cache.store'])->toBe(version_compare(app()->version(), '11.0', '<') ? null : config('cache.default'));
            expect($event['attributes']['cache.operation'])->toBe(CacheOperation::Get);
            expect($event['attributes']['cache.result'])->toBe(CacheResult::Miss);
        },
    ];

    yield 'key written' => [
        fn () => null,
        fn () => cache()->put('some_key', 'some_value'),
        function (array $event) {
            expect($event['type'])->toBe(SpanEventType::Cache);
            expect($event['attributes']['cache.key'])->toBe('some_key');
            expect($event['attributes']['cache.store'])->toBe(version_compare(app()->version(), '11.0', '<') ? null : config('cache.default'));
            expect($event['attributes']['cache.operation'])->toBe(CacheOperation::Set);
            expect($event['attributes']['cache.result'])->toBe(CacheResult::Success);
        },
    ];

    yield 'key forgotten' => [
        fn () => cache()->put('some_key', 'some_value'),
        fn () => cache()->forget('some_key'),
        function (array $event) {
            expect($event['type'])->toBe(SpanEventType::Cache);
            expect($event['attributes']['cache.key'])->toBe('some_key');
            expect($event['attributes']['cache.store'])->toBe(version_compare(app()->version(), '11.0', '<') ? null : config('cache.default'));
            expect($event['attributes']['cache.operation'])->toBe(CacheOperation::Forget);
            expect($event['attributes']['cache.result'])->toBe(CacheResult::Success);
        },
    ];
});

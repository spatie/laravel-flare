<?php

use Illuminate\Support\Facades\Route;
use function Pest\Laravel\get;
use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\Enums\CacheResult;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;

uses(ConfigureFlare::class);

it('records cache operations', function (
    Closure $preRecording,
    Closure $record,
    Closure $assert,
) {
    $preRecording();

    setupFlare();

    Route::get('exception', function () use ($record) {
        $record();

        throw new Exception('This is a failed operation');
    });

    get('exception')->assertStatus(500);

    $fakeSender = FakeSender::instance();

    $fakeSender->assertRequestsSent(1);

    $spanEvents = $fakeSender->getLastPayload()['events'];

    expect($spanEvents)->toHaveCount(1);

    $assert($spanEvents[0]);
})->with('cache recorder');

dataset('cache recorder', function () {
    yield 'cache hit' => [
        fn () => cache()->put('some_key', 'some_value', 60),
        fn () => cache()->get('some_key'),
        function (array $event) {
            expect($event['type'])->toBe(SpanEventType::Cache);
            expect($event['attributes']['cache.key'])->toBe('some_key');
            expect($event['attributes']['cache.store'])->toBe('array');
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
            expect($event['attributes']['cache.store'])->toBe('array');
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
            expect($event['attributes']['cache.store'])->toBe('array');
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
            expect($event['attributes']['cache.store'])->toBe('array');
            expect($event['attributes']['cache.operation'])->toBe(CacheOperation::Forget);
            expect($event['attributes']['cache.result'])->toBe(CacheResult::Success);
        },
    ];
});

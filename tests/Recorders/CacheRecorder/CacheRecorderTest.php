<?php

use Illuminate\Support\Facades\Route;
use function Pest\Laravel\get;
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

    setupFlare(fn (FlareConfig $config) => $config->removeAllRecorders()->cacheEvents());

    Route::get('exception', function () use ($record) {
        $record();

        throw new Exception('This is a failed operation');
    });

    get('exception')->assertStatus(500);

    $fakeSender = FakeSender::instance();

    $fakeSender->assertRequestsSent(1);

    $spanEvents = $fakeSender->getLastPayload()['span_events'];

    expect($spanEvents)->toHaveCount(1);

    $assert($spanEvents[0]);
})->with('cache recorder');

dataset('cache recorder', function () {
    yield 'cache hit' => [
        fn () => cache()->put('some_key', 'some_value', 60),
        fn () => cache()->get('some_key'),
        function (array $event) {
            expect($event['name'])->toBe('Cache hit - some_key');
            expect($event['attributes']['flare.span_event_type'])->toBe(SpanEventType::CacheHit);
            expect($event['attributes']['cache.key'])->toBe('some_key');
            expect($event['attributes']['cache.store'])->toBe('array');
        },
    ];

    yield 'cache miss' => [
        fn () => null,
        fn () => cache()->get('some_key'),
        function (array $event) {
            expect($event['name'])->toBe('Cache miss - some_key');
            expect($event['attributes']['flare.span_event_type'])->toBe(SpanEventType::CacheMiss);
            expect($event['attributes']['cache.key'])->toBe('some_key');
            expect($event['attributes']['cache.store'])->toBe('array');
        },
    ];

    yield 'key written' => [
        fn () => null,
        fn () => cache()->put('some_key', 'some_value'),
        function (array $event) {
            expect($event['name'])->toBe('Cache key written - some_key');
            expect($event['attributes']['flare.span_event_type'])->toBe(SpanEventType::CacheKeyWritten);
            expect($event['attributes']['cache.key'])->toBe('some_key');
            expect($event['attributes']['cache.store'])->toBe('array');
        },
    ];

    yield 'key forgotten' => [
        fn () => cache()->put('some_key', 'some_value'),
        fn () => cache()->forget('some_key'),
        function (array $event) {
            expect($event['name'])->toBe('Cache key forgotten - some_key');
            expect($event['attributes']['flare.span_event_type'])->toBe(SpanEventType::CacheKeyForgotten);
            expect($event['attributes']['cache.key'])->toBe('some_key');
            expect($event['attributes']['cache.store'])->toBe('array');
        },
    ];
});

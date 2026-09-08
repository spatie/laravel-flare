<?php

use Illuminate\Support\Facades\Http;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tests\Shared\FakeApi;

it('records headers as strings, joining multi-value headers', function () {
    Http::fake([
        '*' => Http::response('ok', 200, [
            'Content-Type' => 'application/json',
            'X-Multiple' => ['value1', 'value2'],
        ]),
    ]);

    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    Http::withHeaders([
        'X-Single' => 'single-value',
        'X-Multi' => ['first', 'second'],
    ])->get('https://example.com/endpoint');

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectEnded()
        ->expectType(SpanType::HttpRequest)
        ->expectAttribute('http.request.headers', fn ($headers) => expect($headers)
            ->toHaveKey('X-Single', 'single-value')
            ->toHaveKey('X-Multi', 'first, second')
            ->each()->toBeString())
        ->expectAttribute('http.response.headers', fn ($headers) => expect($headers)
            ->toHaveKey('Content-Type', 'application/json')
            ->toHaveKey('X-Multiple', 'value1, value2')
            ->each()->toBeString());
});

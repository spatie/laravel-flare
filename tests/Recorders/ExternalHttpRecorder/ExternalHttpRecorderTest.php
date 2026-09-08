<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tests\Shared\FakeApi;

it('traces an external http request', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    Http::fake([
        'https://example.com/*' => Http::response('{"ok":true}', 200, ['Content-Type' => 'application/json']),
    ]);

    $flare->tracer->startTrace();

    Http::get('https://example.com/api');

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectAllSpansClosed()
        ->expectSpan(0)
        ->expectName('Http Request - example.com')
        ->expectType(SpanType::HttpRequest)
        ->expectAttribute('url.full', 'https://example.com/api')
        ->expectAttribute('http.request.method', 'GET')
        ->expectAttribute('server.address', 'example.com')
        ->expectAttribute('url.path', '/api')
        ->expectAttribute('http.response.status_code', 200);
});

it('closes a span for every hop of a redirected request', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    Http::fake([
        'https://one.test/*' => Http::response('', 301, ['Location' => 'https://two.test/']),
        'https://two.test/*' => Http::response('', 302, ['Location' => 'https://three.test/']),
        'https://three.test/*' => Http::response('done', 200),
    ]);

    $flare->tracer->startTrace();

    $response = Http::get('https://one.test/');

    $flare->tracer->endTrace();

    expect($response->status())->toBe(200);

    $trace = FakeApi::lastTrace();

    $trace
        ->expectSpanCount(3)
        ->expectAllSpansClosed();

    $trace->expectSpan(0)
        ->expectName('Http Request - one.test')
        ->expectAttribute('url.full', 'https://one.test/')
        ->expectAttribute('http.response.status_code', 301);

    $trace->expectSpan(1)
        ->expectName('Http Request - two.test')
        ->expectAttribute('url.full', 'https://two.test/')
        ->expectAttribute('http.response.status_code', 302);

    $trace->expectSpan(2)
        ->expectName('Http Request - three.test')
        ->expectAttribute('url.full', 'https://three.test/')
        ->expectAttribute('http.response.status_code', 200);
});

it('does not nest the spans of a redirected request', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    Http::fake([
        'https://one.test/*' => Http::response('', 301, ['Location' => 'https://two.test/']),
        'https://two.test/*' => Http::response('ok', 200),
    ]);

    $flare->tracer->startTrace();

    $parent = $flare->tracer->startSpan('Parent');

    Http::get('https://one.test/');

    $flare->tracer->endSpan($parent);

    $flare->tracer->endTrace();

    $trace = FakeApi::lastTrace();

    $trace->expectSpanCount(3);

    $trace->expectSpan(1)->expectParentId($parent->spanId);
    $trace->expectSpan(2)->expectParentId($parent->spanId);
});

it('keeps spans closed when a request made after a redirect is traced', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    Http::fake([
        'https://one.test/*' => Http::response('', 301, ['Location' => 'https://two.test/']),
        'https://two.test/*' => Http::response('ok', 200),
        'https://later.test/*' => Http::response('ok', 200),
    ]);

    $flare->tracer->startTrace();

    $parent = $flare->tracer->startSpan('Parent');

    Http::get('https://one.test/');
    Http::get('https://later.test/');

    $flare->tracer->endSpan($parent);

    $flare->tracer->endTrace();

    $trace = FakeApi::lastTrace();

    $trace
        ->expectSpanCount(4)
        ->expectAllSpansClosed();

    $trace->expectSpan(3)
        ->expectName('Http Request - later.test')
        ->expectAttribute('url.full', 'https://later.test/')
        ->expectParentId($parent->spanId);
});

it('closes the span when a request fails to connect', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    Http::fake([
        'https://gone.test/*' => fn () => throw new ConnectException(
            'Could not resolve host',
            new PsrRequest('GET', 'https://gone.test/'),
        ),
    ]);

    $flare->tracer->startTrace();

    try {
        Http::get('https://gone.test/');
    } catch (ConnectionException) {
        // Expected
    }

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectAllSpansClosed()
        ->expectSpan(0)
        ->expectName('Http Request - gone.test')
        ->expectAttribute('error.type', ConnectException::class);
});

it('closes the span when a request fails but carries a response', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    // Guzzle reports a transfer that died after the response headers arrived
    // through an exception carrying the partial response. Which exception class
    // that is differs between Guzzle 7 and 8, both expose `getResponse`.
    $exception = new class('Transfer closed with outstanding read data remaining') extends RuntimeException {
        public function getResponse(): PsrResponse
        {
            return new PsrResponse(200, ['Content-Length' => '10'], 'partial');
        }
    };

    Http::fake([
        'https://partial.test/*' => fn () => throw $exception,
    ]);

    $flare->tracer->startTrace();

    try {
        Http::get('https://partial.test/');
    } catch (Throwable) {
        // Expected
    }

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectAllSpansClosed()
        ->expectSpan(0)
        ->expectName('Http Request - partial.test')
        ->expectAttribute('http.response.status_code', 200)
        ->expectAttribute('http.response.body.size', 10);
});

it('closes every span of a pooled request', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    Http::fake([
        'https://first.test/*' => Http::response('one', 200),
        'https://second.test/*' => Http::response('two', 200),
        'https://third.test/*' => Http::response('three', 200),
    ]);

    $flare->tracer->startTrace();

    Http::pool(fn ($pool) => [
        $pool->get('https://first.test/'),
        $pool->get('https://second.test/'),
        $pool->get('https://third.test/'),
    ]);

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(3)
        ->expectAllSpansClosed();
});

it('records headers as strings, like every other header attribute', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    Http::fake([
        'https://example.com/*' => Http::response('ok', 200, [
            'Content-Type' => 'application/json',
            'X-Multiple' => ['one', 'two'],
        ]),
    ]);

    $flare->tracer->startTrace();

    Http::withHeaders(['X-Test' => ['first', 'second']])->get('https://example.com/api');

    $flare->tracer->endTrace();

    $attributes = FakeApi::lastTrace()->expectSpan(0)->attributes();

    expect($attributes['http.request.headers']['X-Test'])->toBe('first, second');
    expect($attributes['http.response.headers']['X-Multiple'])->toBe('one, two');
    expect($attributes['http.response.headers']['Content-Type'])->toBe('application/json');
});

<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\Routing;
use Illuminate\Support\Facades\Event;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Symfony\Component\HttpFoundation\Response;

it('records and closes the routing span when a request did not match a route', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $request = Request::create('https://flare.test/missing-route', 'GET');

    $flare->request()?->recordStartFromSymfonyRequest($request);

    Event::dispatch(new Routing($request));
    Event::dispatch(new RequestHandled($request, new Response('', 404)));

    $flare->request()?->recordEndFromSymfonyResponse(new Response('', 404));

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpan(SpanType::Routing)
        ->expectType(SpanType::Routing);

    FakeApi::lastTrace()->expectAllSpansClosed();
});

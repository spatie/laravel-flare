<?php

use Illuminate\Http\Request;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Symfony\Component\HttpFoundation\Response;

it('groups unmatched 4xx responses when ending a request span', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $request = Request::create('https://flare.test/missing-route', 'GET');

    $flare->request()?->recordStartFromSymfonyRequest($request);
    $flare->request()?->recordEndFromSymfonyResponse(new Response('', 404));

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectAttribute('http.route', 'errors::404')
        ->expectAttribute('http.response.status_code', 404);
});

it('does not override an existing route when ending a request span', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $request = Request::create('https://flare.test/users/1', 'GET');

    $flare->request()?->recordStartFromSymfonyRequest($request);
    $flare->request()?->recordEndFromSymfonyResponse(
        new Response('', 404),
        attributes: ['http.route' => 'users/{id}'],
    );

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectAttribute('http.route', 'users/{id}')
        ->expectAttribute('http.response.status_code', 404);
});

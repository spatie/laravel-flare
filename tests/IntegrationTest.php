<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\LaravelFlare\Tests\TestClasses\ExpectSentPayloads;

describe('Laravel integration', function () {
    it('can get a simple welcome page trace', function () {
        $workspace = ExpectSentPayloads::get('/');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()
            ->expectSpanCount(9)
            ->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.request.method', 'GET')
            ->expectHasAttribute('url.full')
            ->expectAttribute('url.scheme', 'http')
            ->expectAttribute('url.path', '/')
            ->expectAttribute('url.query', '')
            ->expectAttribute('flare.entry_point.type', 'web')
            ->expectHasAttribute('flare.entry_point.value')
            ->expectHasAttribute('flare.entry_point.class')
            ->expectHasAttribute('server.address')
            ->expectHasAttribute('server.port')
            ->expectAttribute('user_agent.original', 'GuzzleHttp/7')
            ->expectAttribute('http.request.body.size', 0)
            ->expectHasAttribute('client.address')
            ->expectHasAttribute('http.request.headers')
            ->expectHasAttribute('http.request.session')
            ->expectAttribute('http.route', '/')
            ->expectAttribute('laravel.route.parameters', [])
            ->expectAttribute('laravel.route.middleware', ['web'])
            ->expectHasAttribute('laravel.route.action')
            ->expectHasAttribute('laravel.route.action_type')
            ->expectAttribute('http.response.status_code', 200)
            ->expectHasAttribute('flare.peak_memory_usage');
    });

    it('can handle a case where execution is aborted', function () {
        $workspace = ExpectSentPayloads::get('/abort');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()
            ->expectSpanCount(10)
            ->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)->expectAttribute('http.response.status_code', 403);
    });
})->skipOnWindows();

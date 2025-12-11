<?php

use Illuminate\Support\Facades\DB;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\LaravelFlare\Tests\TestClasses\ExpectSentPayloads;
use Workbench\App\Http\Controllers\InvokableController;
use Workbench\App\Http\Controllers\ResourceController;
use Workbench\Database\Factories\UserFactory;

beforeEach(function () {
    if (PHP_OS_FAMILY === 'Windows') {
        return;
    }

    DB::table('users')->truncate();
    DB::table('cache')->truncate();
    DB::table('jobs')->truncate();
});

describe('Laravel integration', function () {
    // Requests

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

    it('can track invokeable controllers', function () {
        $workspace = ExpectSentPayloads::get('/invokable-controller');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('flare.entry_point.class', InvokableController::class)
            ->expectAttribute('http.route', 'invokable-controller')
            ->expectAttribute('laravel.route.action', InvokableController::class)
            ->expectAttribute('laravel.route.action_type', 'controller');
    });

    it('can track resource controllers', function () {
        $workspace = ExpectSentPayloads::get('/resource-controller');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('flare.entry_point.class', ResourceController::class.'@index')
            ->expectAttribute('http.route', 'resource-controller')
            ->expectAttribute('laravel.route.action', ResourceController::class.'@index')
            ->expectAttribute('laravel.route.action_type', 'controller');
    });

    it('can track named routes', function () {
        $workspace = ExpectSentPayloads::get('/named-route');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'named-route')
            ->expectAttribute('laravel.route.name', 'named-route')
            ->expectAttribute('laravel.route.action', ResourceController::class.'@index')
            ->expectAttribute('laravel.route.action_type', 'controller');
    });

    it('can track a route with parameter', function () {
        $workspace = ExpectSentPayloads::get('/parameter-route/42');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'parameter-route/{id}')
            ->expectAttribute('laravel.route.parameters', ['id' => '42']);
    });

    it('can track a route with an optional parameter (not provided)', function () {
        $workspace = ExpectSentPayloads::get('/optional-parameter-route');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'optional-parameter-route/{id?}')
            ->expectAttribute('laravel.route.parameters', []);
    });

    it('can track a route with model binding missing', function () {
        $workspace = ExpectSentPayloads::get('/model-binding-route/1');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'model-binding-route/{user}')
            ->expectAttribute('laravel.route.parameters', ['user' => '1'])
            ->expectAttribute('http.response.status_code', 404);
    });

    it('can track a route with model found missing', function () {
        $user = UserFactory::new()->create();

        $workspace = ExpectSentPayloads::get("/model-binding-route/{$user->id}");

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'model-binding-route/{user}')
            ->expectAttribute('laravel.route.parameters', function ($value) use ($user) {
                expect($value)->toBeArray();
                expect($value)->toHaveKey('user');
                expect($value['user'])->toEqualCanonicalizing($user->refresh()->toArray());
            });
    });

    it('can handle a request with an injected validation request', function () {
        $postData = [
            'id' => 42,
            'email' => 'joe@spatie.be',
        ];

        $workspace = ExpectSentPayloads::post('/injected-validation-request', $postData);

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.request.method', 'POST')
            ->expectAttribute('http.route', 'injected-validation-request')
            ->expectAttribute('http.request.body.size', 33)
            ->expectAttribute('http.response.status_code', 200);
    });

    it('can handle a request with a failed injected validation request', function () {
        $workspace = ExpectSentPayloads::post('/injected-validation-request', []);

        $workspace->assertSent(traces: 2); // One validation, one redirect after validation failure

        $trace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.request.method', 'POST')
            ->expectAttribute('http.route', 'injected-validation-request')
            ->expectAttribute('http.request.body.size', 2)
            ->expectAttribute('http.response.status_code', 302);
    });

    it('can handle a throttled route', function () {
        ExpectSentPayloads::get('/throttled-route');

        $workspace = ExpectSentPayloads::get('/throttled-route');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'throttled-route')
            ->expectAttribute('http.response.status_code', 429);
    });

    it('can handle a route aborted by middleware', function () {
        $workspace = ExpectSentPayloads::get('/abort-middleware');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'abort-middleware')
            ->expectAttribute('http.response.status_code', 404);
    });

    it('can handle a route failing in before middleware', function () {
        $workspace = ExpectSentPayloads::get('/failing-before-middleware');

        $workspace->assertSent(reports: 1, traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'failing-before-middleware')
            ->expectAttribute('http.response.status_code', 500);

        $workspace->lastReport()
            ->expectExceptionClass(Exception::class)
            ->expectMessage('Failing before middleware');
    });

    it('can handle a route failing in after middleware', function () {
        $workspace = ExpectSentPayloads::get('/failing-after-middleware');

        $workspace->assertSent(reports: 1, traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'failing-after-middleware')
            ->expectAttribute('http.response.status_code', 500);

        $workspace->lastReport()
            ->expectExceptionClass(Exception::class)
            ->expectMessage('Failing after middleware');
    });

    // Queue

    it('can handle a job dispatched after the request', function () {

    })->skip();
})->skipOnWindows();

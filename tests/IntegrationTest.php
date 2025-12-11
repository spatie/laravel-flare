<?php

use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\DynamicComponent;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tests\Shared\ExpectSpan;
use Spatie\FlareClient\Tests\Shared\ExpectSpanEvent;
use Spatie\LaravelFlare\Tests\TestClasses\ExpectSentPayloads;
use Workbench\App\Http\Controllers\InvokableController;
use Workbench\App\Http\Controllers\ResourceController;
use Workbench\App\View\Components\Deeper\DeeperComponent;
use Workbench\App\View\Components\TestInlineComponent;
use Workbench\Database\Factories\UserFactory;

beforeEach(function () {
    if (PHP_OS_FAMILY === 'Windows') {
        return;
    }
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
            ->expectHasAttribute('flare.peak_memory_usage')
            ->expectAttribute('http.response.headers', function ($value) {
                expect($value)->toBeArray();
                expect($value)->toHaveKey('content-type', 'text/html; charset=utf-8');
                expect($value)->toHaveKey('cache-control');
                expect($value)->toHaveKey('date');
                expect($value)->toHaveKey('set-cookie', "<CENSORED:string>");
            })
            ->expectHasAttribute('http.response.body.size')
            ->expectAttribute('http.response.status_code', 200);

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
        DB::table('users')->delete(); // Cleanup from previous tests

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

    it('can handle a JSON response', function () {
        $workspace = ExpectSentPayloads::get('/json-response');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'json-response')
            ->expectAttribute('http.response.headers', function ($value) {
                expect($value)->toBeArray();
                expect($value)->toHaveKey('content-type', 'application/json');
            })
            ->expectAttribute('http.response.body.size', 13)
            ->expectAttribute('http.response.status_code', 200);
    });

    it('can handle a string response', function () {
        $workspace = ExpectSentPayloads::get('/string-response');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'string-response')
            ->expectAttribute('http.response.headers', function ($value) {
                expect($value)->toBeArray();
                expect($value)->toHaveKey('content-type', 'text/html; charset=utf-8');
            })
            ->expectAttribute('http.response.body.size', 11)
            ->expectAttribute('http.response.status_code', 200);
    });

    it('can handle a download response', function () {
        $workspace = ExpectSentPayloads::get('/download-response', waitAtLeastMs: 3000);

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'download-response')
            ->expectAttribute('http.response.headers', function ($value) {
                expect($value)->toBeArray();
                expect($value)->toHaveKey('content-disposition', 'attachment; filename=composer.json');
            })
            ->expectAttribute('http.response.body.size', 0)
            ->expectAttribute('http.response.status_code', 200);
    });

    it('can handle a typed response', function () {
        $workspace = ExpectSentPayloads::get('/typed-response');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'typed-response')
            ->expectAttribute('http.response.headers', function ($value) {
                expect($value)->toBeArray();
                expect($value)->toHaveKey('content-type', 'text/html; charset=utf-8');
            })
            ->expectAttribute('http.response.body.size', 11)
            ->expectAttribute('http.response.status_code', 200);
    });

    it('can handle a view response', function () {
        $workspace = ExpectSentPayloads::get('/view-response-routed');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'view-response-routed')
            ->expectAttribute('http.response.headers', function ($value) {
                expect($value)->toBeArray();
                expect($value)->toHaveKey('content-type', 'text/html; charset=utf-8');
            })
            ->expectAttribute('http.response.body.size', fn ($value) => expect($value)->toBeGreaterThan(0))
            ->expectAttribute('http.response.status_code', 200);

        $trace->expectSpan(SpanType::View)
            ->expectAttribute('view.name', 'welcome')
            ->expectHasAttribute('view.file')
            ->expectHasAttribute('view.data');
    });

    it('can handle a view response with a view returned', function () {
        $workspace = ExpectSentPayloads::get('/view-response');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.route', 'view-response')
            ->expectAttribute('http.response.headers', function ($value) {
                expect($value)->toBeArray();
                expect($value)->toHaveKey('content-type', 'text/html; charset=utf-8');
            })
            ->expectAttribute('http.response.body.size', fn ($value) => expect($value)->toBeGreaterThan(0))
            ->expectAttribute('http.response.status_code', 200);

        $trace->expectSpan(SpanType::View)
            ->expectAttribute('view.name', 'welcome')
            ->expectHasAttribute('view.file')
            ->expectHasAttribute('view.data');
    });

    it('can handle a view with nesting', function () {
        UserFactory::new()->count(3)->create();

        $workspace = ExpectSentPayloads::get('/view-nesting-response');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $trace->expectSpans(
            SpanType::View,
            function (ExpectSpan $span) use (&$nestingSpan) {
                return $nestingSpan = $span
                    ->expectAttribute('view.name', 'nesting')
                    ->expectMissingAttribute('view.loop')
                    ->expectAttribute('view.data', []);
            },
            function (ExpectSpan $span) use (&$nestingSpan) {
                return $span
                    ->expectParentId($nestingSpan)
                    ->expectAttribute('view.name', 'nested')
                    ->expectAttribute('view.loop', '0/2')
                    ->expectAttribute('view.data', []);
            },
            function (ExpectSpan $span) use (&$nestingSpan) {
                return $span
                    ->expectParentId($nestingSpan)
                    ->expectAttribute('view.name', 'nested')
                    ->expectAttribute('view.loop', '1/2')
                    ->expectAttribute('view.data', []);
            },
            function (ExpectSpan $span) use (&$nestingSpan) {
                return $span
                    ->expectParentId($nestingSpan)
                    ->expectAttribute('view.name', 'nested')
                    ->expectAttribute('view.loop', '2/2')
                    ->expectAttribute('view.data', []);
            },
        );
    });

    it('can handle a plain PHP view response', function () {
        $workspace = ExpectSentPayloads::get('/plain-php-view-response');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $trace->expectSpan(SpanType::View)
            ->expectAttribute('view.name', 'plain-php')
            ->expectHasAttribute('view.file')
            ->expectAttribute('view.data', []);
    });

    it('can handle a view component response', function () {
        $workspace = ExpectSentPayloads::get('/view-component');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $trace->expectSpans(
            SpanType::View,
            function (ExpectSpan $span) use (&$componentSpan) {
                return $componentSpan = $span->expectAttribute('view.name', 'component');
            },
            function (ExpectSpan $span) use (&$componentSpan) {
                return $span
                    ->expectParentId($componentSpan)
                    ->expectAttribute('view.name', 'components.test-component')
                    ->expectAttribute('view.component.name', 'workbench::test-component')
                    ->expectAttribute('view.component.inline', false);
            },
            function (ExpectSpan $span) use (&$componentSpan) {
                return $span
                    ->expectParentId($componentSpan)
                    ->expectAttribute('view.name', fn (string $value) => expect($value)->not()->toBe('components.test-inline-component')) // Inline component gets a hashed name
                    ->expectAttribute('view.component.name', 'workbench::test-inline-component')
                    ->expectAttribute('view.component.inline', true)
                    ->expectAttribute('view.component.class', TestInlineComponent::class);
            },
            function (ExpectSpan $span) use (&$componentSpan, &$dynamicComponentSpan) {
                return $dynamicComponentSpan = $span
                    ->expectParentId($componentSpan)
                    ->expectAttribute('view.name', fn (string $value) => expect($value)->not()->toBe('dynamic-component')) // Inline component gets a hashed name
                    ->expectAttribute('view.component.name', 'dynamic-component')
                    ->expectAttribute('view.component.inline', true)
                    ->expectAttribute('view.component.class', DynamicComponent::class);
            },
            function (ExpectSpan $span) use (&$dynamicComponentSpan) {
                return $span
                    ->expectParentId($dynamicComponentSpan)
                    ->expectAttribute('view.name', 'components.test-component')
                    ->expectAttribute('view.component.name', 'workbench::test-component')
                    ->expectAttribute('view.component.inline', false);
            },
            function (ExpectSpan $span) use (&$componentSpan) {
                return $span
                    ->expectParentId($componentSpan)
                    ->expectAttribute('view.name', fn (string $value) => expect($value)->not()->toBe('deeper.deeper-component')) // Inline component gets a hashed name
                    ->expectAttribute('view.component.name', 'workbench::deeper.deeper-component')
                    ->expectAttribute('view.component.inline', true)
                    ->expectAttribute('view.component.class', DeeperComponent::class);
            },
        );
    });

    // Exceptions

    it('can handle an exception route', function () {
        $workspace = ExpectSentPayloads::get('/exception');

        $workspace->assertSent(reports: 1, traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $foundTrackingUuid = null;

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 500)
            ->expectSpanEventCount(1)
            ->expectSpanEvent(0)
            ->expectType(SpanEventType::Exception)
            ->expectAttribute('exception.type', Exception::class)
            ->expectAttribute('exception.message', 'Test exception')
            ->expectAttribute('exception.id', function ($value) use (&$foundTrackingUuid) {
                expect($value)->toBeUuid();

                $foundTrackingUuid = $value;
            });

        $workspace->lastReport()
            ->expectExceptionClass(Exception::class)
            ->expectMessage('Test exception')
            ->expectTrackingUuid($foundTrackingUuid);
    });

    it('can handle a handled exception route', function () {
        $workspace = ExpectSentPayloads::get('/handled-exception');

        $workspace->assertSent(reports: 1, traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200)
            ->expectSpanEventCount(1)
            ->expectSpanEvent(0)
            ->expectType(SpanEventType::Exception)
            ->expectAttribute('exception.handled', true);

        $workspace->lastReport()
            ->expectExceptionClass(Exception::class)
            ->expectMessage('Handled exception')
            ->expectHandled(true);
    });

    it('can handle a view exception route', function () {
        $workspace = ExpectSentPayloads::get('/view-exception');

        $workspace->assertSent(reports: 1, traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 500);

        $trace->expectSpan(7) // The error page generates a massive amount of view spans
        ->expectSpanEventCount(1)
            ->expectSpanEvent(0)
            ->expectType(SpanEventType::Exception);

        $workspace->lastReport()
            ->expectExceptionClass(Exception::class);
    });

    it('can handle a view error route', function () {
        $workspace = ExpectSentPayloads::get('/view-error');

        $workspace->assertSent(reports: 1, traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 500);

        $trace->expectSpan(7) // The error page generates a massive amount of view spans
        ->expectSpanEventCount(1)
            ->expectSpanEvent(0)
            ->expectType(SpanEventType::Exception);

        $workspace->lastReport()
            ->expectExceptionClass(ErrorException::class)
            ->expectLevel('error');
    });

    // Queries

    it('can handle a query route', function () {
        UserFactory::new()->create(['name' => 'John Doe']);

        $workspace = ExpectSentPayloads::get('/query');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $requestSpan = $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $trace->expectSpan(SpanType::Query)
            ->expectParentId($requestSpan)
            ->expectAttribute('db.system', 'sqlite')
            ->expectHasAttribute('db.name')
            ->expectAttribute('db.statement', 'select * from "users" limit 1')
            ->expectAttribute('db.sql.bindings', [])
            ->expectAttribute('laravel.db.connection', 'sqlite');
    });

    it('can handle a route with multiple queries', function () {
        UserFactory::new()->create(['id' => 1, 'name' => 'John']);

        $workspace = ExpectSentPayloads::get('/multiple-queries');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $requestSpan = $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $trace->expectSpans(
            SpanType::Query,
            fn (ExpectSpan $span) => $span
                ->expectParentId($requestSpan)
                ->expectAttribute('db.system', 'sqlite')
                ->expectHasAttribute('db.name')
                ->expectAttribute('db.statement', 'select * from "users" where "id" = ? limit 1')
                ->expectAttribute('db.sql.bindings', [1])
                ->expectAttribute('laravel.db.connection', 'sqlite'),
            fn (ExpectSpan $span) => $span
                ->expectParentId($requestSpan)
                ->expectAttribute('db.system', 'sqlite')
                ->expectHasAttribute('db.name')
                ->expectAttribute('db.statement', 'select * from "users" where "name" = ? limit 1')
                ->expectAttribute('db.sql.bindings', ['John'])
                ->expectAttribute('laravel.db.connection', 'sqlite')
        );
    });

    it('can handle a transaction route', function () {
        $workspace = ExpectSentPayloads::get('/transaction');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $requestSpan = $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $transactionSpan = $trace->expectSpan(SpanType::Transaction)
            ->expectParentId($requestSpan)
            ->expectAttribute('laravel.db.connection', 'sqlite')
            ->expectAttribute('db.transaction.status', 'committed');

        $trace->expectSpan(SpanType::Query)
            ->expectParentId($transactionSpan);
    });

    it('can handle a failing transaction route', function () {
        $workspace = ExpectSentPayloads::get('/failing-transaction');

        $workspace->assertSent(reports: 1, traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $requestSpan = $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 500);

        $transactionSpan = $trace->expectSpan(SpanType::Transaction)
            ->expectParentId($requestSpan)
            ->expectAttribute('laravel.db.connection', 'sqlite')
            ->expectAttribute('db.transaction.status', 'rolled_back');

        $workspace->lastReport()
            ->expectExceptionClass(QueryException::class);
    });

    // Cache

    it('can handle a cache route', function () {
        $workspace = ExpectSentPayloads::get('/cache');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200)
            ->expectSpanEvents(
                SpanEventType::Cache,
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('cache.operation', 'get')
                    ->expectAttribute('cache.result', 'miss')
                    ->expectAttribute('cache.key', 'foo')
                    ->expectAttribute('cache.store', 'database'),
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('cache.operation', 'set')
                    ->expectAttribute('cache.result', 'success')
                    ->expectAttribute('cache.key', 'foo')
                    ->expectAttribute('cache.store', 'database'),
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('cache.operation', 'get')
                    ->expectAttribute('cache.result', 'hit')
                    ->expectAttribute('cache.key', 'foo')
                    ->expectAttribute('cache.store', 'database'),
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('cache.operation', 'get')
                    ->expectAttribute('cache.result', 'hit')
                    ->expectAttribute('cache.key', 'foo')
                    ->expectAttribute('cache.store', 'database'),
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('cache.operation', 'forget')
                    ->expectAttribute('cache.result', 'success')
                    ->expectAttribute('cache.key', 'foo')
                    ->expectAttribute('cache.store', 'database')
            );
    });

    // External HTTP

    it('can handle an HTTP GET request', function () {
        $workspace = ExpectSentPayloads::get('/http-get');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $requestSpan = $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $trace->expectSpan(SpanType::HttpRequest)
            ->expectParentId($requestSpan)
            ->expectAttribute('http.request.method', 'GET')
            ->expectAttribute('url.full', 'https://jsonplaceholder.typicode.com/posts/1')
            ->expectAttribute('server.address', 'jsonplaceholder.typicode.com')
            ->expectAttribute('url.scheme', 'https')
            ->expectAttribute('url.path', '/posts/1')
            ->expectAttribute('http.request.body.size', 0)
            ->expectAttribute('http.response.status_code', 200)
            ->expectAttribute('http.response.body.size', fn ($value) => expect($value)->toBeGreaterThan(0));
    });

    it('can handle an HTTP POST request', function () {
        $workspace = ExpectSentPayloads::get('/http-post');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $requestSpan = $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $trace->expectSpan(SpanType::HttpRequest)
            ->expectParentId($requestSpan)
            ->expectAttribute('http.request.method', 'POST')
            ->expectAttribute('url.full', 'https://jsonplaceholder.typicode.com/posts')
            ->expectAttribute('server.address', 'jsonplaceholder.typicode.com')
            ->expectAttribute('url.scheme', 'https')
            ->expectAttribute('url.path', '/posts')
            ->expectAttribute('http.request.body.size', 13)
            ->expectAttribute('http.response.status_code', 201)
            ->expectAttribute('http.response.body.size', fn ($value) => expect($value)->toBeGreaterThan(0));
    });

    it('can handle a failing HTTP request', function () {
        $workspace = ExpectSentPayloads::get('/http-fail');

        $workspace->assertSent(traces: 1, reports: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $requestSpan = $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 500);

        $requestSpan->expectSpanEventCount(1)
            ->expectSpanEvent(0)
            ->expectType(SpanEventType::Exception);

        $trace->expectSpan(SpanType::HttpRequest)
            ->expectParentId($requestSpan)
            ->expectAttribute('http.request.method', 'GET')
            ->expectAttribute('url.full', 'https://does-not-exist-invalid-domain-12345.com')
            ->expectMissingAttribute('http.response.status_code')
            ->expectMissingAttribute('http.response.body.size')
            ->expectAttribute('error.type', ConnectionException::class);

        $workspace->lastReport()
            ->expectExceptionClass(ConnectionException::class);
    });

    it('can keep track of file system operations', function () {
        $workspace = ExpectSentPayloads::get('/filesystem');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $requestSpan = $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $trace->expectSpans(
            SpanType::Filesystem,
            fn (ExpectSpan $span) => $span
                ->expectParentId($requestSpan)
                ->expectAttribute('filesystem.operation', 'put')
                ->expectAttribute('filesystem.path', 'example.txt')
                ->expectAttribute('filesystem.contents.size', '8 B')
                ->expectAttribute('filesystem.operation.success', true),
            fn (ExpectSpan $span) => $span
                    ->expectParentId($requestSpan)
                    ->expectAttribute('filesystem.operation', 'get')
                    ->expectAttribute('filesystem.path', 'example.txt')
                    ->expectAttribute('filesystem.contents.size', '8 B'),
        );
    });
})->skipOnWindows();

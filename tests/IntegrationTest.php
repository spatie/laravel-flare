<?php

use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\DynamicComponent;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tests\Shared\ExpectReportEvent;
use Spatie\FlareClient\Tests\Shared\ExpectSpan;
use Spatie\FlareClient\Tests\Shared\ExpectSpanEvent;
use Spatie\LaravelFlare\Enums\SpanType as LaravelSpanType;
use Spatie\LaravelFlare\Tests\TestClasses\ExpectSentPayloads;
use Workbench\App\Http\Controllers\InvokableController;
use Workbench\App\Http\Controllers\ResourceController;
use Workbench\App\Jobs\BatchedJob;
use Workbench\App\Jobs\DeletedJob;
use Workbench\App\Jobs\ExitingJob;
use Workbench\App\Jobs\NestedJob;
use Workbench\App\Jobs\ReleaseJob;
use Workbench\App\Jobs\SuccesJob;
use Workbench\App\Livewire\Counter;
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
                expect($value)->toHaveKey('content-type');
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
                expect($value)->toHaveKey('content-type');
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
                expect($value)->toHaveKey('content-type');
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
                expect($value)->toHaveKey('content-type');
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
                expect($value)->toHaveKey('content-type');
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

    // File System

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

    // Queues

    it('can handle a queued job', function () {
        $workspace = ExpectSentPayloads::get('/trigger-job', waitUntilAllJobsAreProcessed: true);

        $workspace->assertSent(traces: 2);

        $httpTrace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $requestSpan = $httpTrace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $queuingSpan = $httpTrace->expectSpan(LaravelSpanType::Queueing)
            ->expectParentId($requestSpan)
            ->expectAttribute('laravel.job.queue.connection_name', 'database')
            ->expectAttribute('laravel.job.queue.name', 'default')
            ->expectHasAttribute('laravel.job.uuid')
            ->expectAttribute('laravel.job.name', SuccesJob::class)
            ->expectAttribute('laravel.job.class', SuccesJob::class);

        $jobTrace = $workspace->trace(1)
            ->expectSpanCount(4); // Job, transaction: select and remove job

        $jobTrace->expectSpan(LaravelSpanType::Job)
            ->expectParentId($queuingSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.queue.connection_name', 'database')
            ->expectAttribute('laravel.job.queue.name', 'default')
            ->expectAttribute('laravel.job.name', SuccesJob::class)
            ->expectAttribute('laravel.job.class', SuccesJob::class)
//            ->expectAttribute('laravel.job.attempts', 1) // TODO, not on queue database worker?
            ->expectAttribute('laravel.job.success', true)
            ->expectHasAttribute('laravel.job.uuid')
            ->expectHasAttribute('flare.peak_memory_usage');
    });

    it('can handle a queued closure job', function () {
        $workspace = ExpectSentPayloads::get('/trigger-job-closure', waitUntilAllJobsAreProcessed: true);

        $workspace->assertSent(traces: 2);

        $httpTrace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $requestSpan = $httpTrace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $queuingSpan = $httpTrace->expectSpan(LaravelSpanType::Queueing)
            ->expectParentId($requestSpan)
            ->expectAttribute('laravel.job.queue.connection_name', 'database')
            ->expectAttribute('laravel.job.queue.name', 'default')
            ->expectHasAttribute('laravel.job.uuid')
            ->expectAttribute(
                'laravel.job.name',
                fn (string $value) => expect($value)->toStartWith('Closure (')
            )
            ->expectAttribute('laravel.job.class', CallQueuedClosure::class);

        $jobTrace = $workspace->trace(1)
            ->expectSpanCount(4);

        $jobTrace->expectSpan(LaravelSpanType::Job)
            ->expectParentId($queuingSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.queue.connection_name', 'database')
            ->expectAttribute('laravel.job.queue.name', 'default')
            ->expectAttribute(
                'laravel.job.name',
                fn (string $value) => expect($value)->toStartWith('Closure (')
            )
            ->expectAttribute('laravel.job.class', CallQueuedClosure::class)
            ->expectAttribute('laravel.job.success', true)
            ->expectHasAttribute('laravel.job.uuid')
            ->expectHasAttribute('flare.peak_memory_usage');
    });

    it('can handle a failed queued closure job', function () {
        $workspace = ExpectSentPayloads::get('/trigger-failed-job-closure', waitUntilAllJobsAreProcessed: true);

        $workspace->assertSent(reports: 1, traces: 2);

        $httpTrace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $requestSpan = $httpTrace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $queuingSpan = $httpTrace->expectSpan(LaravelSpanType::Queueing)
            ->expectParentId($requestSpan);

        $jobTrace = $workspace->trace(1)
            ->expectSpanCount(5); // Job, transaction: select and remove job, plus the failed attempt
        $jobSpan = $jobTrace->expectSpan(LaravelSpanType::Job)
            ->expectParentId($queuingSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.success', false)
            ->expectSpanEventCount(1);

        $jobExceptionEvent = $jobSpan->expectSpanEvent(0)
            ->expectType(SpanEventType::Exception);

        $workspace->lastReport()
            ->expectExceptionClass(Exception::class)
            ->expectMessage('Job failed')
            ->expectTrackingUuid($jobExceptionEvent->attributes()['exception.id'])
            ->expectEventCount(1)
            ->expectEvent(0)
            ->expectType(LaravelSpanType::Job)
            ->expectStart($jobSpan->span['startTimeUnixNano'])
            ->expectEnd($jobSpan->span['endTimeUnixNano'])
            ->expectAttribute('laravel.job.queue.connection_name', 'database')
            ->expectAttribute('laravel.job.queue.name', 'default')
            ->expectAttribute(
                'laravel.job.name',
                fn (string $value) => expect($value)->toStartWith('Closure (')
            )
            ->expectAttribute('laravel.job.class', CallQueuedClosure::class)
            ->expectAttribute('laravel.job.success', false)
            ->expectHasAttribute('laravel.job.uuid')
            ->expectHasAttribute('flare.peak_memory_usage');
    });

    it('can handle a synchronous closure job', function () {
        $workspace = ExpectSentPayloads::get('/trigger-sync-job-closure');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $trace->expectSpanCount(0, LaravelSpanType::Job); // No queueing span for sync dispatch
    });

    it('can handle a job dispatched after response', function () {
        $workspace = ExpectSentPayloads::get('/trigger-job-after-response', waitUntilAllJobsAreProcessed: true);

        $workspace->assertSent(traces: 1);

        $httpTrace = $workspace->trace(0);

        $httpTrace->expectLaravelRequestLifecycle(terminatingSpans: function (int &$spanIndex, ExpectSpan $terminatingSpan) use ($httpTrace) {
            $terminatingSpan->expectType(SpanType::ApplicationTerminating);

            $httpTrace->expectSpan($spanIndex)
                ->expectParentId($terminatingSpan)
                ->expectType(LaravelSpanType::Job)
                ->expectAttribute('laravel.job.queue.connection_name', 'sync')
                ->expectAttribute('laravel.job.queue.name', 'sync')
                ->expectAttribute('laravel.job.name', SuccesJob::class)
                ->expectAttribute('laravel.job.class', SuccesJob::class)
                ->expectAttribute('laravel.job.success', true)
                ->expectHasAttribute('laravel.job.uuid')
                ->expectHasAttribute('flare.peak_memory_usage');
        });

        $httpTrace->expectSpan(SpanType::Request)->expectAttribute('http.response.status_code', 200);

        $httpTrace->expectSpanCount(0, LaravelSpanType::Queueing);
    });

    it('can handle a failed job dispatched after response', function () {
        $workspace = ExpectSentPayloads::get('/trigger-fail-job-after-response');

        $workspace->assertSent(reports: 1, traces: 1);

        $httpTrace = $workspace->trace(0);

        $httpTrace->expectLaravelRequestLifecycle(terminatingSpans: function (int &$spanIndex, ExpectSpan $terminatingSpan) use ($httpTrace) {
            $terminatingSpan->expectType(SpanType::ApplicationTerminating);

            $httpTrace->expectSpan($spanIndex)
                ->expectParentId($terminatingSpan)
                ->expectType(LaravelSpanType::Job)
                ->expectAttribute('laravel.job.queue.connection_name', 'sync')
                ->expectAttribute('laravel.job.queue.name', 'sync')
                ->expectAttribute('laravel.job.success', false)
                ->expectHasAttribute('laravel.job.uuid')
                ->expectHasAttribute('flare.peak_memory_usage')
                ->expectSpanEventCount(1)
                ->expectSpanEvent(0)
                ->expectType(SpanEventType::Exception);
        });

        $httpTrace->expectSpan(SpanType::Request)->expectAttribute('http.response.status_code', 200);

        $httpTrace->expectSpanCount(0, LaravelSpanType::Queueing);

        $workspace->lastReport()
            ->expectExceptionClass(Exception::class);
    });

    it('can handle a job chain', function () {
        $workspace = ExpectSentPayloads::get('/trigger-job-chain', waitUntilAllJobsAreProcessed: true);

        $workspace->assertSent(traces: 4); // 1 HTTP request + 3 jobs

        $httpTrace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $requestSpan = $httpTrace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $queuingSpan = $httpTrace
            ->expectSpan(LaravelSpanType::Queueing)
            ->expectParentId($requestSpan)
            ->expectTraceId($requestSpan->span['traceId']);

        $job1Span = $workspace->trace(1)
            ->expectSpan(LaravelSpanType::Job)
            ->expectTraceId($requestSpan->span['traceId'])
            ->expectParentId($queuingSpan);

        $queue1Span = $workspace->trace(1)
            ->expectSpan(LaravelSpanType::Queueing)
            ->expectTraceId($requestSpan->span['traceId'])
            ->expectParentId($job1Span);

        $job2Span = $workspace->trace(2)
            ->expectSpan(LaravelSpanType::Job)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectParentId($queue1Span);

        $queue2Span = $workspace->trace(2)
            ->expectSpan(LaravelSpanType::Queueing)
            ->expectTraceId($requestSpan->span['traceId'])
            ->expectParentId($job2Span);

        $workspace->trace(3)
            ->expectSpan(LaravelSpanType::Job)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectParentId($queue2Span);
    });

    it('can handle a synchronous job', function () {
        $workspace = ExpectSentPayloads::get('/trigger-sync-job');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $requestSpan = $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $trace->expectSpan(LaravelSpanType::Job)
            ->expectParentId($requestSpan)
            ->expectAttribute('laravel.job.queue.connection_name', 'sync')
            ->expectAttribute('laravel.job.queue.name', 'sync')
            ->expectAttribute('laravel.job.name', SuccesJob::class)
            ->expectAttribute('laravel.job.class', SuccesJob::class)
            ->expectAttribute('laravel.job.success', true)
            ->expectHasAttribute('laravel.job.uuid')
            ->expectHasAttribute('flare.peak_memory_usage');

        $trace->expectSpanCount(0, LaravelSpanType::Queueing);
    });

    it('can handle a retrying job', function () {
        $workspace = ExpectSentPayloads::get('/trigger-retrying-job', waitUntilAllJobsAreProcessed: true);

        $workspace->assertSent(reports: 3, traces: 4); // 3 reports (one per attempt), 1 HTTP request + 3 job attempts

        $httpTrace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $requestSpan = $httpTrace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $queuingSpan = $httpTrace->expectSpan(LaravelSpanType::Queueing)
            ->expectParentId($requestSpan);

        $workspace->trace(1)
            ->expectSpan(LaravelSpanType::Job)
            ->expectParentId($queuingSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.success', false)
            ->expectSpanEventCount(1)
            ->expectSpanEvent(0)
            ->expectType(SpanEventType::Exception);

        $workspace->trace(2)
            ->expectSpan(LaravelSpanType::Job)
            ->expectParentId($queuingSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.success', false)
            ->expectSpanEventCount(1)
            ->expectSpanEvent(0)
            ->expectType(SpanEventType::Exception);

        $workspace->trace(3)
            ->expectSpan(LaravelSpanType::Job)
            ->expectParentId($queuingSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.success', false)
            ->expectSpanEventCount(1)
            ->expectSpanEvent(0)
            ->expectType(SpanEventType::Exception);

        $workspace->lastReport()
            ->expectExceptionClass(Exception::class)
            ->expectMessage('Whoops here we go again');
    });

    it('can handle a job with multiple attempts until success', function () {
        $workspace = ExpectSentPayloads::get('/trigger-attempts-job', waitUntilAllJobsAreProcessed: true);

        $workspace->assertSent(reports: 4, traces: 6); // 4 reports (attempts 1-4), 1 HTTP request + 5 job attempts

        $httpTrace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $requestSpan = $httpTrace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $queuingSpan = $httpTrace->expectSpan(LaravelSpanType::Queueing)
            ->expectParentId($requestSpan);

        for ($i = 1; $i <= 4; $i++) {
            $workspace->trace($i)
                ->expectSpan(LaravelSpanType::Job)
                ->expectParentId($queuingSpan)
                ->expectTraceId($queuingSpan->span['traceId'])
                ->expectAttribute('laravel.job.success', false)
                ->expectSpanEventCount(1)
                ->expectSpanEvent(0)
                ->expectType(SpanEventType::Exception);
        }

        $workspace->trace(5)
            ->expectSpan(LaravelSpanType::Job)
            ->expectParentId($queuingSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.success', true);
    });

    it('can handle an ignored job', function () {
        $workspace = ExpectSentPayloads::get('/trigger-ignored-job', waitUntilAllJobsAreProcessed: true);

        $workspace->assertSent(traces: 1); // 1 HTTP request

        $httpTrace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $httpTrace->expectSpan(SpanType::Request)->expectAttribute('http.response.status_code', 200);

        $httpTrace->expectSpanCount(0, LaravelSpanType::Queueing);

        // Job being stored in database is also ignored
        $httpTrace->expectSpanCount(0, SpanType::Query);
    });

    it('can handle a nested job', function () {
        $workspace = ExpectSentPayloads::get('/trigger-nested-job', waitUntilAllJobsAreProcessed: true);

        $workspace->assertSent(traces: 3); // 1 HTTP request + 2 jobs

        $httpTrace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $requestSpan = $httpTrace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $queuingSpan = $httpTrace->expectSpan(LaravelSpanType::Queueing)
            ->expectParentId($requestSpan)
            ->expectAttribute('laravel.job.class', NestedJob::class);

        $nestedJobTrace = $workspace->trace(1);

        $nestedJobSpan = $nestedJobTrace->expectSpan(LaravelSpanType::Job)
            ->expectParentId($queuingSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.class', NestedJob::class)
            ->expectAttribute('laravel.job.success', true);

        $nestedQueuingSpan = $nestedJobTrace->expectSpan(LaravelSpanType::Queueing)
            ->expectParentId($nestedJobSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.class', SuccesJob::class);

        $workspace->trace(2)
            ->expectSpan(LaravelSpanType::Job)
            ->expectParentId($nestedQueuingSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.class', SuccesJob::class)
            ->expectAttribute('laravel.job.success', true);
    });

    it('can handle an exiting job', function () {
        $workspace = ExpectSentPayloads::get('/trigger-exiting-job', waitUntilAllJobsAreProcessed: false);

        $workspace->assertSent(traces: 1); // 1 HTTP request, Job will never be sent

        $httpTrace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $requestSpan = $httpTrace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $httpTrace->expectSpan(LaravelSpanType::Queueing)
            ->expectParentId($requestSpan)
            ->expectAttribute('laravel.job.class', ExitingJob::class);
    })->skip('This kills the queue process, so do not test this');

    it('can handle a released job', function () {
        $workspace = ExpectSentPayloads::get('/trigger-release-job', waitUntilAllJobsAreProcessed: true);

        $workspace->assertSent(traces: 3, reports: 1); // 1 HTTP request + 1 job that releases itself

        $httpTrace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $requestSpan = $httpTrace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $queuingSpan = $httpTrace->expectSpan(LaravelSpanType::Queueing)
            ->expectParentId($requestSpan)
            ->expectAttribute('laravel.job.class', ReleaseJob::class);

        $jobTrace = $workspace->trace(1);

        $jobTrace->expectSpan(LaravelSpanType::Job)
            ->expectParentId($queuingSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.class', ReleaseJob::class)
            ->expectAttribute('laravel.job.success', true)
            ->expectAttribute('laravel.job.released', true);

        $jobTrace->expectSpanCount(0, LaravelSpanType::Queueing);

        $workspace->trace(2)
            ->expectSpan(LaravelSpanType::Job)
            ->expectParentId($queuingSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.class', ReleaseJob::class)
            ->expectAttribute('laravel.job.success', false)
            ->expectSpanEventCount(1)
            ->expectSpanEvent(0)
            ->expectType(SpanEventType::Exception)
            ->expectAttribute('exception.type', MaxAttemptsExceededException::class);
    });

    it('can handle a deleted job', function () {
        $workspace = ExpectSentPayloads::get('/trigger-deleted-job', waitUntilAllJobsAreProcessed: true);

        $workspace->assertSent(traces: 2); // 1 HTTP request + 1 job that deletes itself

        $httpTrace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $requestSpan = $httpTrace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $queuingSpan = $httpTrace->expectSpan(LaravelSpanType::Queueing)
            ->expectParentId($requestSpan)
            ->expectAttribute('laravel.job.class', DeletedJob::class);

        $jobTrace = $workspace->trace(1);

        $jobTrace->expectSpan(LaravelSpanType::Job)
            ->expectParentId($queuingSpan)
            ->expectTraceId($queuingSpan->span['traceId'])
            ->expectAttribute('laravel.job.class', DeletedJob::class)
            ->expectAttribute('laravel.job.success', true)
            ->expectAttribute('laravel.job.deleted', true);
    });

    it('can handle a batch with some failing jobs', function () {
        $workspace = ExpectSentPayloads::get('/trigger-batch', waitUntilAllJobsAreProcessed: true);

        $workspace->assertSent(reports: 1, traces: 7); // 1 HTTP request + 4 batched jobs

        $httpTrace = $workspace->trace(0)->expectLaravelRequestLifecycle();

        $requestSpan = $httpTrace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $transactionSpan = $httpTrace->expectSpan(SpanType::Transaction)
            ->expectParentId($requestSpan)
            ->expectAttribute('db.transaction.status', 'committed');

        $httpTrace->expectSpans(
            LaravelSpanType::Queueing,
            fn (ExpectSpan $span) => $span->expectParentId($transactionSpan)->expectAttribute('laravel.job.class', BatchedJob::class),
            fn (ExpectSpan $span) => $span->expectParentId($transactionSpan)->expectAttribute('laravel.job.class', BatchedJob::class),
            fn (ExpectSpan $span) => $span->expectParentId($transactionSpan)->expectAttribute('laravel.job.class', BatchedJob::class),
            fn (ExpectSpan $span) => $span->expectParentId($transactionSpan)->expectAttribute('laravel.job.class', BatchedJob::class),
            fn (ExpectSpan $span) => $span->expectParentId($transactionSpan)->expectAttribute('laravel.job.class', BatchedJob::class),
        );

        // Success Job
        foreach ([1, 2, 4, 6] as $successJobTrace) {
            $workspace->trace($successJobTrace)
                ->expectSpan(LaravelSpanType::Job)
                ->expectAttribute('laravel.job.success', true)
                ->expectAttribute('laravel.job.class', BatchedJob::class);
        }

        // Failing Job
        $workspace->trace(3)
            ->expectSpan(LaravelSpanType::Job)
            ->expectAttribute('laravel.job.class', BatchedJob::class)
            ->expectAttribute('laravel.job.success', false)
            ->expectHasAttribute('laravel.job.batch_id')
            ->expectSpanEventCount(1)
            ->expectSpanEvent(0)
            ->expectType(SpanEventType::Exception)
            ->expectAttribute('exception.type', Exception::class)
            ->expectAttribute('exception.message', 'Batched job failed');

        $addingJobTrace = $workspace->trace(5);

        $addingJobTrace->expectSpan(LaravelSpanType::Job)
            ->expectAttribute('laravel.job.class', BatchedJob::class)
            ->expectAttribute('laravel.job.success', true)
            ->expectAttribute('laravel.job.properties', ['shouldFail' => false, 'shouldAddAnotherJob' => true]);

        $addingJobTrace->expectSpan(LaravelSpanType::Queueing)
            ->expectAttribute('laravel.job.class', BatchedJob::class)
            ->expectAttribute('laravel.job.properties', ['shouldFail' => false, 'shouldAddAnotherJob' => false])
            ->expectHasAttribute('laravel.job.batch_id');

        $workspace->lastReport()
            ->expectExceptionClass(Exception::class)
            ->expectMessage('Batched job failed');
    });

    // Livewire

    it('can handle a basic livewire component', function () {
        $workspace = ExpectSentPayloads::get('/livewire');

        $workspace->assertSent(traces: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $requestSpan = $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 200);

        $componentSpan = $trace
            ->expectSpan(LaravelSpanType::LivewireComponent)
            ->expectParentId($requestSpan)
            ->expectAttribute('livewire.component.class', Counter::class)
            ->expectAttribute('livewire.component.name', 'workbench.app.livewire.counter')
            ->expectAttribute('view.name', 'livewire.counter')
            ->expectHasAttribute('livewire.component.phase.mounting')
            ->expectHasAttribute('livewire.component.phase.rendering')
            ->expectHasAttribute('livewire.component.phase.dehydrating');

        $trace->expectSpan(LaravelSpanType::LivewireComponentMounting)
            ->expectParentId($componentSpan)
            ->expectAttribute('livewire.component.name', 'workbench.app.livewire.counter');

        $componentRenderingSpan = $trace->expectSpan(LaravelSpanType::LivewireComponentRendering)
            ->expectParentId($componentSpan)
            ->expectAttribute('livewire.component.name', 'workbench.app.livewire.counter');

        $trace->expectSpans(
            SpanType::View,
            fn (ExpectSpan $span) => $span
                ->expectParentId($componentRenderingSpan)
                ->expectAttribute('view.name', 'livewire.counter')
                ->expectAttribute('view.name', 'livewire.counter')
                ->expectHasAttribute('view.file'),
            fn (ExpectSpan $span) => $span
                ->expectParentId($requestSpan)
                ->expectAttribute('view.name', 'components.layouts.app')
                ->expectHasAttribute('view.file'),
        );

        $trace->expectSpan(LaravelSpanType::LivewireComponentDehydrating)
            ->expectParentId($componentSpan)
            ->expectAttribute('livewire.component.name', 'workbench.app.livewire.counter');
    });

    // Logs

    it('can handle log messages', function () {
        $workspace = ExpectSentPayloads::get('/logs');

        $workspace->assertSent(traces: 1, logs: 1, reports: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $responseSpan = $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 500)
            ->expectSpanEventCount(0, SpanEventType::Log);

        $log = $workspace->log(0)->expectLogCount(8);

        $log->expectLog(0)
            ->expectBody('Debug message')
            ->expectAttribute('log.context', [])
            ->expectSeverityText('debug')
            ->expectSeverityNumber(5)
            ->expectMatchesSpan($responseSpan)
            ->expectSampling();

        $log->expectLog(1)
            ->expectBody('Info message')
            ->expectAttribute('log.context', [])
            ->expectSeverityText('info')
            ->expectSeverityNumber(9)
            ->expectMatchesSpan($responseSpan)
            ->expectSampling();

        $log->expectLog(2)
            ->expectBody('Notice message')
            ->expectAttribute('log.context', [])
            ->expectSeverityText('notice')
            ->expectSeverityNumber(10)
            ->expectMatchesSpan($responseSpan)
            ->expectSampling();

        $log->expectLog(3)
            ->expectBody('Warning message')
            ->expectAttribute('log.context', [])
            ->expectSeverityText('warning')
            ->expectSeverityNumber(13)
            ->expectMatchesSpan($responseSpan)
            ->expectSampling();

        $log->expectLog(4)
            ->expectBody('Error message')
            ->expectAttribute('log.context', [])
            ->expectSeverityText('error')
            ->expectSeverityNumber(17)
            ->expectMatchesSpan($responseSpan)
            ->expectSampling();

        $log->expectLog(5)
            ->expectBody('Critical message')
            ->expectAttribute('log.context', [])
            ->expectSeverityText('critical')
            ->expectSeverityNumber(18)
            ->expectMatchesSpan($responseSpan)
            ->expectSampling();

        $log->expectLog(6)
            ->expectBody('Alert message')
            ->expectAttribute('log.context', [])
            ->expectSeverityText('alert')
            ->expectSeverityNumber(19)
            ->expectMatchesSpan($responseSpan)
            ->expectSampling();

        $log->expectLog(7)
            ->expectBody('Emergency message')
            ->expectAttribute('log.context', [])
            ->expectSeverityText('emergency')
            ->expectSeverityNumber(21)
            ->expectMatchesSpan($responseSpan)
            ->expectSampling();

        $workspace->lastReport()
            ->expectEventCount(7) // We only start logging from info level
            ->expectEvents(
                SpanEventType::Log,
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('log.message', 'Info message')
                    ->expectAttribute('log.level', 'info')
                    ->expectAttribute('log.context', [])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('log.message', 'Notice message')
                    ->expectAttribute('log.level', 'notice')
                    ->expectAttribute('log.context', [])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('log.message', 'Warning message')
                    ->expectAttribute('log.level', 'warning')
                    ->expectAttribute('log.context', [])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('log.message', 'Error message')
                    ->expectAttribute('log.level', 'error')
                    ->expectAttribute('log.context', [])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('log.message', 'Critical message')
                    ->expectAttribute('log.level', 'critical')
                    ->expectAttribute('log.context', [])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('log.message', 'Alert message')
                    ->expectAttribute('log.level', 'alert')
                    ->expectAttribute('log.context', [])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('log.message', 'Emergency message')
                    ->expectAttribute('log.level', 'emergency')
                    ->expectAttribute('log.context', [])
                    ->expectMissingEnd()
            );
    });

    // Glows

    it('can handle glow messages', function () {
        $workspace = ExpectSentPayloads::get('/glows');

        $workspace->assertSent(traces: 1, reports: 1);

        $trace = $workspace->lastTrace()->expectLaravelRequestLifecycle();

        $trace->expectSpan(SpanType::Request)
            ->expectAttribute('http.response.status_code', 500)
            ->expectSpanEvents(
                SpanEventType::Glow,
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('glow.name', 'Debug')
                    ->expectAttribute('glow.level', 'debug')
                    ->expectAttribute('glow.context', ['foo' => 'bar']),
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('glow.name', 'Info')
                    ->expectAttribute('glow.level', 'info')
                    ->expectAttribute('glow.context', ['foo' => 'bar']),
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('glow.name', 'Notice')
                    ->expectAttribute('glow.level', 'notice')
                    ->expectAttribute('glow.context', ['foo' => 'bar']),
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('glow.name', 'Warning')
                    ->expectAttribute('glow.level', 'warning')
                    ->expectAttribute('glow.context', ['foo' => 'bar']),
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('glow.name', 'Error')
                    ->expectAttribute('glow.level', 'error')
                    ->expectAttribute('glow.context', ['foo' => 'bar']),
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('glow.name', 'Critical')
                    ->expectAttribute('glow.level', 'critical')
                    ->expectAttribute('glow.context', ['foo' => 'bar']),
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('glow.name', 'Alert')
                    ->expectAttribute('glow.level', 'alert')
                    ->expectAttribute('glow.context', ['foo' => 'bar']),
                fn (ExpectSpanEvent $event) => $event
                    ->expectAttribute('glow.name', 'Emergency')
                    ->expectAttribute('glow.level', 'emergency')
                    ->expectAttribute('glow.context', ['foo' => 'bar'])
            );

        $workspace->lastReport()
            ->expectEventCount(8)
            ->expectEvents(
                SpanEventType::Glow,
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('glow.name', 'Debug')
                    ->expectAttribute('glow.level', 'debug')
                    ->expectAttribute('glow.context', ['foo' => 'bar'])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('glow.name', 'Info')
                    ->expectAttribute('glow.level', 'info')
                    ->expectAttribute('glow.context', ['foo' => 'bar'])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('glow.name', 'Notice')
                    ->expectAttribute('glow.level', 'notice')
                    ->expectAttribute('glow.context', ['foo' => 'bar'])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('glow.name', 'Warning')
                    ->expectAttribute('glow.level', 'warning')
                    ->expectAttribute('glow.context', ['foo' => 'bar'])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('glow.name', 'Error')
                    ->expectAttribute('glow.level', 'error')
                    ->expectAttribute('glow.context', ['foo' => 'bar'])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('glow.name', 'Critical')
                    ->expectAttribute('glow.level', 'critical')
                    ->expectAttribute('glow.context', ['foo' => 'bar'])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('glow.name', 'Alert')
                    ->expectAttribute('glow.level', 'alert')
                    ->expectAttribute('glow.context', ['foo' => 'bar'])
                    ->expectMissingEnd(),
                fn (ExpectReportEvent $event) => $event
                    ->expectAttribute('glow.name', 'Emergency')
                    ->expectAttribute('glow.level', 'emergency')
                    ->expectAttribute('glow.context', ['foo' => 'bar'])
                    ->expectMissingEnd()
            );
    });

    // Context

    it('can handle context', function () {
        $workspace = ExpectSentPayloads::get('/context');

        $workspace->assertSent(traces: 1, logs: 1, reports: 1);

        $workspace->lastTrace()
            ->expectLaravelRequestLifecycle()
            ->expectSpan(SpanType::Application)
            ->expectAttribute('context.custom', [
                'single_flare_entry' => 'value',
                'multi_flare_entry' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
            ])
            ->expectAttribute('context.laravel', [
                'single_entry' => 'value',
            ]);

        $workspace->log(0)
            ->expectLogCount(1)
            ->expectLog(0)
            ->expectAttribute('log.context', [
                'log_context_key' => 'log_context_value',
            ])
            ->expectAttribute('context.custom', [
                'single_flare_entry' => 'value',
                'multi_flare_entry' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
            ])
            ->expectAttribute('context.laravel', [
                'single_entry' => 'value',
            ]);

        $workspace->lastReport()
            ->expectAttribute('context.exception', [
                'info' => 'Additional context information',
                'code' => 1234,
            ])
            ->expectAttribute('context.custom', [
                'single_flare_entry' => 'value',
                'multi_flare_entry' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
            ])
            ->expectAttribute('context.laravel', [
                'single_entry' => 'value',
            ]);
    });
})->skipOnWindows();

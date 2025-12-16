<?php

use Composer\InstalledVersions;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Monolog\Level;
use Spatie\FlareClient\Flare;
use Spatie\LaravelFlare\Facades\Flare as FlareFacade;
use Workbench\App\Exceptions\ContextException;
use Workbench\App\Http\Controllers\InvokableController;
use Workbench\App\Http\Controllers\ResourceController;
use Workbench\App\Http\Middleware\Abort404Middleware;
use Workbench\App\Http\Middleware\FailingAfterMiddleware;
use Workbench\App\Http\Middleware\FailingBeforeMiddleware;
use Workbench\App\Http\Requests\ValidationRequest;
use Workbench\App\Jobs\AttemptTestJob;
use Workbench\App\Jobs\BatchedJob;
use Workbench\App\Jobs\DeletedJob;
use Workbench\App\Jobs\ExitingJob;
use Workbench\App\Jobs\FailJob;
use Workbench\App\Jobs\IgnoredJob;
use Workbench\App\Jobs\NestedJob;
use Workbench\App\Jobs\ReleaseJob;
use Workbench\App\Jobs\SuccesJob;
use Workbench\App\Livewire\Counter;
use Workbench\App\Livewire\Full;
use Workbench\App\Livewire\MountException;
use Workbench\App\Livewire\Nested;
use Workbench\App\Livewire\Wired;
use Workbench\App\Models\User;

// Requests

Route::get('/', function () {
    return view('welcome');
});

Route::get('abort', function () {
    abort(403);
});

Route::get('invokable-controller', InvokableController::class);
Route::get('resource-controller', [ResourceController::class, 'index']);

Route::get('named-route', [ResourceController::class, 'index'])->name('named-route');

Route::get('parameter-route/{id}', fn ($id) => 'User {$id}');
Route::get('optional-parameter-route/{id?}', fn ($id = null) => "User {$id}");
Route::get('model-binding-route/{user}', fn (User $user) => "User {$user->name}");

Route::post('injected-validation-request', fn (ValidationRequest $request) => $request->all());

Route::get('throttled-route', fn () => "Throttled")->middleware(ThrottleRequests::with(0));
Route::get('abort-middleware', fn () => 'Middleware aborted')->middleware(Abort404Middleware::class);
Route::get('failing-before-middleware', fn () => 'Failing before middleware')->middleware(FailingBeforeMiddleware::class);
Route::get('failing-after-middleware', fn () => 'Failing before middleware')->middleware(FailingAfterMiddleware::class);

Route::get('json-response', fn () => ['foo' => 'bar']);
Route::get('string-response', fn () => 'Hello World');
Route::get('download-response', fn () => response()->download(base_path('composer.json')));
Route::get('typed-response', fn () => response('Hello World'));
Route::view('view-response-routed', 'welcome');

Route::get('view-response', fn () => view('welcome'));

Route::get('view-nesting-response', fn () => view('nesting', [
    'users' => [
        ['name' => 'Alice'],
        ['name' => 'Bob'],
        ['name' => 'Charlie'],
    ]
]));
Route::get('plain-php-view-response', fn () => view('plain-php', [
    'name' => 'Bob',
]));
Route::view('view-component', 'component');

Route::get('random-status', function () {
    $status = random_int(200, 599);

    return response('Status: '.$status, $status);
})->name('random-status');

// Exceptions
Route::get('exception', fn () => throw new Exception('Test exception'));
Route::get('handled-exception', function () {
    try {
        throw new Exception('Handled exception');
    } catch (Exception $e) {
        report($e);

        return 'Exception handled';
    }
});
Route::view('view-exception', 'exception');
Route::view('view-error', 'error', );

// Queries

Route::get('query', function () {
    $user = User::first();

    return "Hello ".$user->name;
});

Route::get('multiple-queries', function () {
    $user = User::where('id', 1)->first();
    $user = User::where('name', 'John')->first();

    return "Ran 2 queries";
});

Route::get('transaction', function () {
    DB::transaction(function () {
        User::create(['name' => fake()->name, 'email' => fake()->email, 'password' => bcrypt('password')]);
    });

    return 'Transaction committed';
});

Route::get('failing-transaction', function () {
    DB::transaction(function () {
        User::create([]);
    });

    return 'Transaction failed';
});


// Cache

Route::get('cache', function () {
    cache()->get('foo'); // Miss
    cache()->put('foo', 'bar'); // Put
    cache()->get('foo'); // Hit
    cache()->get('foo'); // Second Hit
    cache()->forget('foo'); // Forget

    return 'Cache test';
});

// External  http

Route::get('http-get', fn () => "Http response: ".Http::get('https://jsonplaceholder.typicode.com/posts/1')->body());
Route::get('http-post', fn () => "Http response: ".Http::post('https://jsonplaceholder.typicode.com/posts', ['foo' => 'bar'])->body());
Route::get('http-fail', fn () => "Http response: ".Http::get('https://does-not-exist-invalid-domain-12345.com')->body());

// Filesystem

Route::get('filesystem', function () {
    Storage::disk('test')->put('example.txt', 'Contents');

    return Storage::disk('test')->get('example.txt');
});

// Queue

Route::get('trigger-job', function () {
    (new SuccesJob())->dispatch();

    return 'Dispatched';
});

Route::get('trigger-job-closure', function () {
    dispatch(function () {
       dump('Job executed');
    });

    return 'Dispatched';
});

Route::get('trigger-failed-job-closure', function () {
    dispatch(function () {
        throw new Exception('Job failed');
    });

    return 'Dispatched';
});

Route::get('trigger-sync-job-closure', function () {
    dispatch_sync(function () {
        ray('Job executed');
    });

    return 'Dispatched';
});

Route::get('trigger-job-after-response', function () {
    (new SuccesJob())->dispatchAfterResponse();

    return 'Dispatched';
});

Route::get('trigger-fail-job-after-response', function () {
    (new FailJob())->dispatchAfterResponse();

    return 'Dispatched';
});

Route::get('trigger-job-chain', function () {
    Bus::chain([
        new SuccesJob(),
        new SuccesJob(),
        new SuccesJob(),
    ])->dispatch();

    return 'Dispatched';
});

Route::get('trigger-sync-job', function () {
    dispatch_sync(new SuccesJob());

    return 'Dispatched';
});

Route::get('trigger-retrying-job', function () {
    dispatch(new FailJob());

    return 'Dispatched';
});

Route::get('trigger-attempts-job', function () {
    dispatch(new AttemptTestJob());

    return 'Dispatched';
});

Route::get('trigger-ignored-job', function () {
    dispatch(new IgnoredJob());

    return 'Dispatched';
});

Route::get('trigger-nested-job', function () {
    dispatch(new NestedJob());

    return 'Dispatched';
});

Route::get('trigger-exiting-job', function () {
    dispatch(new ExitingJob());

    return 'Dispatched';
});

Route::get('trigger-release-job', function () {
    dispatch(new ReleaseJob());

    return 'Dispatched';
});

Route::get('trigger-deleted-job', function () {
    dispatch(new DeletedJob());

    return 'Dispatched';
});

Route::get('trigger-batch', function () {
    Bus::batch([
        BatchedJob::success(),
        BatchedJob::success(),
        BatchedJob::failed(),
        BatchedJob::success(),
        BatchedJob::addingAnotherJob()
    ])->allowFailures()->dispatch();

    return 'Dispatched';
});

// Livewire

Route::get('livewire', Counter::class);
Route::get('livewire-nested', Nested::class);
Route::get('livewire-full/{name?}', Full::class);
Route::get('livewire-wired', Wired::class);
Route::get('livewire-old-route', Counter::class);
Route::get('livewire-mount-exception', MountException::class);

if(version_compare(InstalledVersions::getVersion('livewire/livewire'), '4.0.0', '>=')) {
    Route::livewire('livewire-route', Counter::class);
};

// Logs

Route::get('logs', function () {
    Log::debug('Debug message');
    Log::info('Info message');
    Log::notice('Notice message');
    Log::warning('Warning message');
    Log::error('Error message');
    Log::critical('Critical message');
    Log::alert('Alert message');
    Log::emergency('Emergency message');

    throw new Exception('All logs recorded');

    return 'ok';
});

// Glows

Route::get('glows', function (){
    FlareFacade::glow()->record('Debug', Level::Debug, ['foo' => 'bar']);
    FlareFacade::glow()->record('Info', Level::Info, ['foo' => 'bar']);
    FlareFacade::glow()->record('Notice', Level::Notice, ['foo' => 'bar']);
    FlareFacade::glow()->record('Warning', Level::Warning, ['foo' => 'bar']);
    FlareFacade::glow()->record('Error', Level::Error, ['foo' => 'bar']);
    FlareFacade::glow()->record('Critical', Level::Critical, ['foo' => 'bar']);
    FlareFacade::glow()->record('Alert', Level::Alert, ['foo' => 'bar']);
    FlareFacade::glow()->record('Emergency', Level::Emergency, ['foo' => 'bar']);

    throw new Exception('All glows recorded');

    return 'ok';
});

// Context

Route::get('context', function (){
    Context::add('single_entry', 'value');
    Context::addHidden('single_hidden_entry', 'hidden_value');


    FlareFacade::context('single_flare_entry', 'value');
    FlareFacade::context('multi_flare_entry', [
        'key1' => 'value1',
        'key2' => 'value2',
    ]);

    Log::info('Logging with context', [
        'log_context_key' => 'log_context_value',
    ]);

    throw ContextException::create();
});

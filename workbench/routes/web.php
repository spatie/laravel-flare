<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\FlareClient\Flare;
use Workbench\App\Http\Controllers\InvokableController;
use Workbench\App\Http\Controllers\ResourceController;
use Workbench\App\Http\Requests\ValidationRequest;
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

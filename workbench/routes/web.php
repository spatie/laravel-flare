<?php

use Illuminate\Support\Facades\Route;
use Spatie\FlareClient\Flare;

Route::get('/', function () {
    return view('welcome');
});

Route::get('abort', function () {
    abort(403);
});

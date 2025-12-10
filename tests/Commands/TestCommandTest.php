<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Spatie\LaravelFlare\Facades\Flare;

it('can execute the test command when a flare key is present with a Laravel handler configuration', function () {
    setupFlare();

    app()->extend(ExceptionHandler::class, function (Handler $handler) {
        Flare::handles(new Exceptions($handler));

        return $handler;
    });

    $this->artisan('flare:test')->assertOk();
});

<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Spatie\LaravelFlare\Facades\Flare;

it('can execute the test command', function () {
    setupFlare();

    config()->set('logging.channels.flare', ['driver' => 'flare']);
    config()->set('logging.default', 'flare');

    app()->extend(ExceptionHandler::class, function (Handler $handler) {
        Flare::handles(new Exceptions($handler));

        return $handler;
    });

    $this->artisan('flare:test')->assertOk();
});

it('fails when no flare key is set', function () {
    config()->set('flare.key', null);

    setupFlare(withoutApiKey: true);

    $this->artisan('flare:test')
        ->expectsOutputToContain('Flare key not specified')
        ->assertFailed();
});

it('fails when the log channel is not configured', function () {
    setupFlare();

    $this->artisan('flare:test --logs')
        ->expectsOutputToContain('No logging channel')
        ->assertFailed();
});

it('fails when the log channel exists but is not in the default stack', function () {
    setupFlare();

    config()->set('logging.channels.flare', ['driver' => 'flare']);
    config()->set('logging.default', 'single');

    $this->artisan('flare:test --logs')
        ->expectsOutputToContain('is not part of your default logging stack')
        ->assertFailed();
});

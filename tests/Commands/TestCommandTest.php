<?php

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Exceptions\Handler;
use Spatie\LaravelFlare\Facades\Flare;


it('can execute the test command when a flare key is present with a Laravel handler configuration', function () {
    withFlareKey();

    app()->extend(Handler::class, function (Handler $handler) {
        Flare::handles(new Exceptions($handler));

        return $handler;
    });

    $this->artisan('flare:test')->assertOk();
})->skip(fn () => version_compare(app()->version(), '11.0.0', '<'));

it('will fail the test command when config is missing', function () {
    withFlareKey();

    $this->artisan('flare:test')->assertFailed();
});

// Helpers
function withFlareKey(): void
{
    test()->withFlareKey = true;

    test()->refreshApplication();
}

function getEnvironmentSetUp($app)
{
    if (test()->withFlareKey) {
        config()->set('flare.key', 'some-key');
    }
}

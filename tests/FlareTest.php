<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Spatie\FlareClient\Api;
use Spatie\FlareClient\Flare;
use Spatie\LaravelFlare\Tests\Mocks\FakeClient;

beforeEach(function () {
    $this->fakeClient = FakeClient::setUp();

    Artisan::call('view:clear');

    app()['config']['logging.channels.flare'] = [
        'driver' => 'flare',
    ];

    config()->set('logging.channels.flare.driver', 'flare');
    config()->set('logging.default', 'flare');
    config()->set('flare.key', 'some-key');

    $this->useTime('2019-01-01 12:34:56');

    View::addLocation(__DIR__.'/stubs/views');
});

it('can manually report exceptions', function () {
    \Spatie\LaravelFlare\Facades\Flare::sendReportsImmediately();

    \Spatie\LaravelFlare\Facades\Flare::report(new Exception());

    $this->fakeClient->assertRequestsSent(1);
});

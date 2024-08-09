<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Spatie\FlareClient\Api;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;
use Spatie\LaravelFlare\Tests\Mocks\FakeClient;
use Spatie\FlareClient\Tests\Shared\FakeSender;

uses(ConfigureFlare::class);

beforeEach(function () {
    Artisan::call('view:clear');

    app()['config']['logging.channels.flare'] = [
        'driver' => 'flare',
    ];

    config()->set('logging.channels.flare.driver', 'flare');
    config()->set('logging.default', 'flare');
    config()->set('flare.key', 'some-key');

    FakeTime::setup('2019-01-01 12:34:56');

    View::addLocation(__DIR__.'/stubs/views');
});

it('can manually report exceptions', function () {
    $flare = setupFlare();

    $flare->report(new Exception());

    FakeSender::instance()->assertRequestsSent(1);
});

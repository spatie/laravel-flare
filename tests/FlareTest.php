<?php

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Spatie\FlareClient\Disabled\DisabledFlare;
use Spatie\FlareClient\Disabled\DisabledTracer;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\LaravelFlare\Facades\Flare as FlareFacade;
use Spatie\LaravelFlare\FlareServiceProvider;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;

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


it('can report exceptions using the Laravel report helper', function () {
    setupFlare();

    report(new Exception());

    FakeSender::instance()->assertRequestsSent(1);
});

it('will register a version of the disabled Flare client if no API key is set', function () {
    bootupDisabledFlare();

    $flare = app(Flare::class);

    expect($flare)->toBeInstanceOf(DisabledFlare::class);
    expect($flare->tracer())->toBeInstanceOf(DisabledTracer::class);
});

it('will allow handling reports with disabled Flare (yet nothing will be sent or recorder)', function (){
    bootupDisabledFlare();

    FlareFacade::handles();

    report(new Exception());

    expect(true)->toBeTrue();
});

function bootupDisabledFlare()
{
    config()->set('flare.key', '');

    $provider = new FlareServiceProvider(app());

    $provider->register();
    $provider->boot();
}

<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Spatie\FlareClient\Disabled\DisabledFlare;
use Spatie\FlareClient\Disabled\DisabledTracer;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\LaravelFlare\Facades\Flare as FlareFacade;
use Spatie\LaravelFlare\FlareServiceProvider;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;

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

    FakeApi::assertSent(reports: 1);
});


it('can report exceptions using the Laravel report helper', function () {
    setupFlare();

    report(new Exception());

    FakeApi::assertSent(reports: 1);
});

it('will not trace, log or report when flare is disabled', function () {
    setupFlare(withoutApiKey: true, alwaysSampleTraces: true);

    $flare = app(Flare::class);

    // Todo: extend this with more checks

    report(new Exception());

    Log::critical("test");

    FakeApi::assertSent(reports: 0, traces: 0, logs: 0);
});

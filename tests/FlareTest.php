<?php

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\LaravelFlare\Facades\Flare as FlareFacade;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\FlareServiceProvider;

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

it('runs configuring callbacks against the config before it is used to boot Flare', function () {
    FlareServiceProvider::flushConfigurationCallbacks();
    config()->set('flare.key', 'some-key');

    FlareServiceProvider::configure(fn (FlareConfig $config) => $config->applicationName = 'first');
    FlareFacade::configure(function (FlareConfig $config) {
        $config->applicationName = 'from-hook';
        $config->configureResource(fn (Resource $resource) => $resource->addAttribute('custom.attribute', 'value'));
    });

    app()->register(new FlareServiceProvider(resolve(Application::class)));

    expect(app(FlareConfig::class)->applicationName)->toBe('from-hook');
    expect(app(Resource::class)->attributes)->toHaveKey('custom.attribute', 'value');

    FlareServiceProvider::flushConfigurationCallbacks();
});

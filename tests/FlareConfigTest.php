<?php

use Spatie\LaravelFlare\FlareConfig;

it('censors the session and remember me cookies', function () {
    config()->set('flare', require __DIR__.'/../config/flare.php');
    config()->set('session.cookie', 'my_app_session');

    expect(FlareConfig::fromLaravelConfig()->censorCookieNames)->toBe([
        'my_app_session',
        'remember_*',
    ]);
});

it('censors the remember me cookies when a published config has no cookie names', function () {
    $flareConfig = require __DIR__.'/../config/flare.php';

    unset($flareConfig['censor']['cookie_names']);

    config()->set('flare', $flareConfig);
    config()->set('session.cookie', 'my_app_session');

    expect(FlareConfig::fromLaravelConfig()->censorCookieNames)->toBe([
        'my_app_session',
        'remember_*',
    ]);
});

it('ignores invalid cookie names in the config', function () {
    config()->set('flare', require __DIR__.'/../config/flare.php');
    config()->set('flare.censor.cookie_names', null);
    config()->set('session.cookie', 'my_app_session');

    expect(FlareConfig::fromLaravelConfig()->censorCookieNames)->toBe([
        'my_app_session',
    ]);
});

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

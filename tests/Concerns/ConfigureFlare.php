<?php

namespace Spatie\LaravelFlare\Tests\Concerns;

use Spatie\FlareClient\Tests\Shared\FakeSender;

trait ConfigureFlare
{
    protected function getPackageProviders($app)
    {
        config()->set('flare.key', 'dummy-key');
        config()->set('flare.sender.class', FakeSender::class);

        return []; // We'll boot Flare with the setupFlare method
    }
}

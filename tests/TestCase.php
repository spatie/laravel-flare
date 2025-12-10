<?php

namespace Spatie\LaravelFlare\Tests;

use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\LaravelFlare\FlareServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use MakesHttpRequests;


    protected function getPackageProviders($app)
    {
        config()->set('flare.key', 'dummy-key');

        return [];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Flare' => Flare::class,
        ];
    }
}

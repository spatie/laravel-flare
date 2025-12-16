<?php

namespace Spatie\LaravelFlare\Tests;

use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelFlare\Facades\Flare;

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

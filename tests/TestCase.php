<?php

namespace Spatie\LaravelFlare\Tests;

use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\LaravelFlare\FlareServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use MakesHttpRequests;

    protected $fakeClient = null;

    protected function setUp(): void
    {
        // ray()->newScreen($this->getName());

        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        config()->set('flare.key', 'dummy-key');
        config()->set('flare.sender.class', FakeSender::class);

        return [FlareServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Flare' => Flare::class,
        ];
    }
}

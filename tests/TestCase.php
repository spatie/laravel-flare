<?php

namespace Spatie\LaravelFlare\Tests;

use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\Request;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\LaravelFlare\FlareServiceProvider;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

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

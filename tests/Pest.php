<?php

use Dotenv\Dotenv;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Tests\Shared\FakeTraceExporter;
use Spatie\LaravelFlare\Facades\Flare as FlareFacade;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\FlareServiceProvider;
use Spatie\LaravelFlare\Support\CollectsResolver;
use Spatie\LaravelFlare\Support\TracingKernel;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;
use Spatie\LaravelFlare\Tests\TestCase;

uses(TestCase::class)->beforeEach(function () {
    FakeSender::instance()->reset();
    FakeIds::reset();
})->in(__DIR__);

if (file_exists(__DIR__.'/../.env')) {
    $dotEnv = Dotenv::createImmutable(__DIR__.'/..');

    $dotEnv->load();
}

function canRunOpenAiTest(): bool
{
    if (empty(env('OPEN_API_KEY'))) {
        return false;
    }

    return true;
}

/**
 * @param ?Closure(FlareConfig):void $closure
 */
function setupFlare(
    ?Closure $closure = null,
    bool $sendReportsImmediately = true,
    bool $handleErrorsWithFlare = true,
): Flare {
    if (! in_array(ConfigureFlare::class, trait_uses_recursive(test()->target))) {
        throw new Exception('Make sure the test uses the `ConfigureFlare` trait');
    }

    $config = new FlareConfig(
        apiToken: 'fake-api-key',
        sendReportsImmediately: $sendReportsImmediately,
        collectsResolver: CollectsResolver::class,
    );

    $config->useDefaults();

    $config->trace(false);

    $config->sender = FakeSender::class;
    $config->traceExporter = FakeTraceExporter::class;

    if(FakeTime::isSetup()){
        $config->time = FakeTime::class;
    }

    if(FakeIds::isSetup()){
        $config->ids = FakeIds::class;
    }

    if ($closure) {
        $closure($config);
    }

    app()->singleton(FlareConfig::class, fn () => $config);

    app()->register(FlareServiceProvider::class);

    $flare = app()->make(Flare::class);

    if ($handleErrorsWithFlare) {
        FlareFacade::handles(new Exceptions(app(ExceptionHandler::class)));
    }

    return $flare;
}

/**
 * @param ?Closure(FlareConfig):void $closure
 */
function setupFlareForTracing(
    ?Closure $closure = null,
    bool $sendReportsImmediately = true,
    bool $runKernelCallbacks = false,
): Flare {
    return setupFlare(function (FlareConfig $config) use ($runKernelCallbacks, $closure) {
        if ($runKernelCallbacks === false) {
            TracingKernel::$run = false;
        }

        $config->trace(true);
        $config->alwaysSampleTraces();

        if ($closure) {
            $closure($config);
        }
    }, $sendReportsImmediately);
}

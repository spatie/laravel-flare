<?php

use Dotenv\Dotenv;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\LaravelFlare\Facades\Flare as FlareFacade;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\FlareMiddleware\AddJobInformation;
use Spatie\LaravelFlare\FlareServiceProvider;
use Spatie\LaravelFlare\Support\CollectsResolver;
use Spatie\LaravelFlare\Tests\TestCase;

uses(TestCase::class)->beforeEach(function () {
    FakeIds::reset();
    FakeApi::reset();
    AddJobInformation::clearLatestJobInfo();
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
    bool $withoutApiKey = false,
    bool $alwaysSampleTraces = false,
    bool $isUsingSubtasks = false,
): Flare {
    $config = new FlareConfig(
        apiToken: $withoutApiKey ? null : 'fake-api-key',
        collectsResolver: CollectsResolver::class,
    );

    $config->useDefaults();

    $config->trace(false);
    $config->log(true);

    if ($alwaysSampleTraces) {
        $config->trace(true);
        $config->alwaysSampleTraces();
    }

    $config->api = FakeApi::class;

    if (FakeTime::isSetup()) {
        $config->time = FakeTime::class;
    }

    if (FakeIds::isSetup()) {
        $config->ids = FakeIds::class;
    }

    if ($closure) {
        $closure($config);
    }

    app()->singleton(FlareConfig::class, fn () => $config);

    $provider = new FlareServiceProvider(
        resolve(Application::class),
        isUsingSubtasksClosure: $isUsingSubtasks ? fn () => true : null,
    );

    app()->register($provider);

    $flare = app()->make(Flare::class);

    FlareFacade::handles(new Exceptions(app(ExceptionHandler::class)));

    return $flare;
}

<?php

namespace Spatie\LaravelFlare\Support;

use Illuminate\Contracts\Foundation\Application;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Time\Time;
use Spatie\FlareClient\Time\TimeHelper;

class TracingKernel
{
    public static ?int $appRegisteredTime = null;

    public static bool $run = true;

    public static function registerCallbacks(Application $app): void
    {
        if (self::$run === false || self::isCompositeProcess($app)) {
            return;
        }

        $app->registered(function () use ($app) {
            self::$appRegisteredTime = $app->get(Time::class)->getCurrentTime();
        });
    }

    public static function bootCallbacks(Application $app): void
    {
        if (self::$run === false || self::isCompositeProcess($app)) {
            return;
        }

        $flare = $app->make(Flare::class);

        $app->booted(function () use ($flare) {
            self::appBooted($flare);
        });

        $app->terminating(function () use ($app, $flare) {
            self::appTerminating($app, $flare);
        });

        self::startPotentialTrace($flare);
    }

    protected static function startPotentialTrace(
        Flare $flare
    ): void {
        $start = TimeHelper::phpMicroTime(
            defined('LARAVEL_START') ? LARAVEL_START : $_SERVER['REQUEST_TIME_FLOAT']
        );

        $flare->application()->recordStart(time: $start);

        if (self::$appRegisteredTime === null) {
            return;
        }

        $flare->application()->recordRegistration(
            start: $start,
            end: self::$appRegisteredTime
        );

        $flare->application()->recordBooting(time: self::$appRegisteredTime);
    }

    protected static function appBooted(Flare $flare): void
    {
        $flare->application()->recordBooted();
    }

    protected static function appTerminating(Application $app, Flare $flare): void
    {
        $flare->application()->recordTerminating();

        $app->terminating(function () use ($app, $flare) {
            self::appTerminated($app, $flare);
        });
    }

    public static function appTerminated(Application $app, Flare $flare): void
    {
        $flare->application()->recordTerminated();
        $flare->application()->recordEnd();
    }

    protected static function isCompositeProcess(Application $app): bool
    {
        return $app->runningConsoleCommand(['horizon:work', 'queue:work', 'serve', 'octane:start']);
    }
}

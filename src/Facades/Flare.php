<?php

namespace Spatie\LaravelFlare\Facades;

use Closure;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\Facades\Facade;
use Spatie\FlareClient\Flare as FlareClient;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Throwable;

/**
 * @method static FlareClient context(string|array $key, mixed $value = null)
 * @method static FlareClient sendReportsImmediately(bool $sendReportsImmediately = true)
 * @method static FlareClient withApplicationVersion(string|Closure $version)
 * @method static FlareClient filterExceptionsUsing(Closure $filterExceptionsCallable)
 * @method static FlareClient filterReportsUsing(Closure $filterReportsCallable)
 * @method static GlowRecorder glow()
 *
 * @see \Spatie\FlareClient\Flare
 */
class Flare extends Facade
{
    protected static function getFacadeAccessor()
    {
        return FlareClient::class;
    }

    /**
     * @param Exceptions $exceptions
     *
     * @return void
     */
    public static function handles(Exceptions $exceptions): void
    {
        $exceptions->reportable(static function (Throwable $exception): FlareClient {
            $flare = app(FlareClient::class);

            $flare->report($exception);

            return $flare;
        });
    }
}

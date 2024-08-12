<?php

namespace Spatie\LaravelFlare\Facades;

use Closure;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\Facades\Facade;
use Spatie\FlareClient\Flare as FlareClient;
use Spatie\LaravelFlare\FlareConfig;
use Throwable;

/**
 * @method static void glow(string $name, string $messageLevel = \Spatie\FlareClient\Enums\MessageLevels::INFO, array $metaData = [])
 * @method static void context($key, $value)
 * @method static void group(string $groupName, array $properties)
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
     * @param Closure(FlareConfig): void $configure
     *
     * @return void
     */
    public static function handles(Exceptions $exceptions, ?Closure $configure = null): void
    {
        if ($configure !== null) {
            app()->singleton(FlareConfig::class, function () use ($configure) {
                $config = FlareConfig::make(
                    env('FLARE_KEY'),
                );

                $configure($config);

                return $config;
            });
        }

        $exceptions->reportable(static function (Throwable $exception): FlareClient {
            $flare = app(FlareClient::class);

            $flare->report($exception);

            return $flare;
        });
    }
}

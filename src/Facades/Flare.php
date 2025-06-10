<?php

namespace Spatie\LaravelFlare\Facades;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\Facades\Facade;
use Spatie\FlareClient\Flare as FlareClient;
use Spatie\FlareClient\Recorders\ApplicationRecorder\ApplicationRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\ResponseRecorder\ResponseRecorder;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\Recorders\FilesystemRecorder\FilesystemRecorder;
use Spatie\LaravelFlare\Recorders\RoutingRecorder\RoutingRecorder;
use Throwable;

/**
 * @method static FlareClient context(string|mixed[] $key, mixed $value = null)
 * @method static FlareClient sendReportsImmediately(bool $sendReportsImmediately = true)
 * @method static FlareClient withApplicationVersion(string|Closure $version)
 * @method static FlareClient filterExceptionsUsing(Closure $filterExceptionsCallable)
 * @method static FlareClient filterReportsUsing(Closure $filterReportsCallable)
 * @method static GlowRecorder|null glow()
 * @method static FilesystemRecorder|null filesystem()
 * @method static ApplicationRecorder application()
 * @method static RoutingRecorder|null routing()
 * @method static ResponseRecorder|null response()
 * @method static Tracer tracer()
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
    public static function handles(?Exceptions $exceptions = null): void
    {
        $reportable = static function (Throwable $exception): ?FlareClient {
            $config = app(FlareConfig::class);

            if ($config->apiToken === null) {
                return null;
            }

            $flare = app(FlareClient::class);

            $flare->report($exception);

            return $flare;
        };

        if ($exceptions) {
            $exceptions->reportable($reportable);
        }

        $handler = app(ExceptionHandler::class);

        if (method_exists($handler, 'reportable')) {
            $handler->reportable($reportable);
        }
    }
}

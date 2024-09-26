<?php

namespace Spatie\LaravelFlare\Support;

use Illuminate\Contracts\Foundation\Application;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Enums\SpanType;

class TracingKernel
{
    use UsesTime;

    public static ?int $appRegisteredTime = null;

    public static bool $run = true;

    public static function registerCallbacks(Application $app): void
    {
        if (self::$run === false || self::isCompositeProcess($app)) {
            return;
        }

        $app->registered(function () {
            self::$appRegisteredTime = self::getCurrentTime();
        });
    }

    public static function bootCallbacks(Application $app): void
    {
        if (self::$run === false || self::isCompositeProcess($app)) {
            return;
        }

        $tracer = $app->make(Tracer::class);

        $app->booted(function () use ($tracer) {
            self::appBooted($tracer);
        });

        $app->terminating(function () use ($tracer) {
            self::appTerminated($tracer);
        });

        self::startPotentialTrace($tracer);
    }

    protected static function startPotentialTrace(
        Tracer $tracer
    ): void {
        $tracer->potentialStartTrace();

        if (! $tracer->isSampling()) {
            return;
        }

        $start = TimeHelper::phpMicroTime(
            defined('LARAVEL_START') ? LARAVEL_START : $_SERVER['REQUEST_TIME_FLOAT']
        );

        $tracer->startSpan(
            'Laravel Application',
            start: $start,
            attributes: [
                'flare.span_type' => SpanType::Application,
            ],
        );

        if (self::$appRegisteredTime === null) {
            return;
        }

        $tracer->startSpan(
            'App Registration',
            start: $start,
            end: self::$appRegisteredTime,
            attributes: [
                'flare.span_type' => SpanType::Registration,
            ],
        );

        $tracer->startSpan(
            'App Boot',
            start: self::$appRegisteredTime,
            attributes: [
                'flare.span_type' => SpanType::Boot,
            ],
        );
    }

    protected static function appBooted(Tracer $tracer): void
    {
        if (! $tracer->isSampling()) {
            return;
        }

        if (! $tracer->hasCurrentSpan(SpanType::Boot)) {
            return;
        }

        $tracer->endSpan();
    }

    protected static function appTerminated(Tracer $tracer): void
    {
        if (! $tracer->isSampling()) {
            return;
        }

        if (! $tracer->hasCurrentSpan(SpanType::Application)) {
            return;
        }

        $tracer->endSpan();
        $tracer->endTrace();
    }

    protected static function isCompositeProcess(Application $app): bool
    {
        return $app->runningConsoleCommand(['octane:start', 'horizon:work', 'queue:work', 'serve']);
    }
}

<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Spans\Span;

class AddJobInformation implements FlareMiddleware
{
    public static ?string $usedTrackingUuid = null;

    public static ?Span $latestJob = null;

    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        if ($latestJob = static::$latestJob) {
            $report->addAttributes([
                'flare.entry_point.type' => EntryPointType::Queue,
                'flare.entry_point.value' => $latestJob->attributes['laravel.job.name'] ?? null,
                'flare.entry_point.class' => $latestJob->attributes['laravel.job.class'] ?? null,
            ]);

            $report->span($latestJob);

            static::$latestJob = null;
        }

        if (static::$usedTrackingUuid) {
            $report->trackingUuid(static::$usedTrackingUuid);

            static::$usedTrackingUuid = null;
        }

        return $next($report);
    }

    public static function clearLatestJobInfo(): void
    {
        self::$latestJob = null;
        self::$usedTrackingUuid = null;
    }

    public static function setLatestJob(
        Span $job,
    ): void {
        self::$latestJob = $job;
    }

    public static function setUsedTrackingUuid(
        string $uuid,
    ): void {
        self::$usedTrackingUuid = $uuid;
    }
}

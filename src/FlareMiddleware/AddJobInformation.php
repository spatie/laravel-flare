<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;

class AddJobInformation implements FlareMiddleware
{
    public static ?array $currentJob = null;

    public function handle(ReportFactory $report, Closure $next): Closure|ReportFactory
    {
        if (static::$currentJob) {
            $report->addAttributes([
                'flare.entry_point.type' => EntryPointType::Queue,
                'flare.entry_point.value' => static::$currentJob['laravel.job.name'],
                'flare.entry_point.class' => static::$currentJob['laravel.job.class'] ?? null,
            ]);

            static::$currentJob = null;
        }

        return $next($report);
    }
}

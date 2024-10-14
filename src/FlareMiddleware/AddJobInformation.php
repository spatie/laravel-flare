<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;
use Spatie\LaravelFlare\Enums\SpanType;

class AddJobInformation implements FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next): Closure|ReportFactory
    {
        foreach ($report->events as $event) {
            if (($event->attributes['span.type'] ?? null) === SpanType::Job) {
                $report->addAttributes([
                    'flare.entry_point.type' => EntryPointType::Queue,
                    'flare.entry_point.value' => $event->attributes['laravel.job.name'],
                    'flare.entry_point.class' => $event->attributes['laravel.job.class'] ?? null,
                ]);

                return $next($report);
            }
        }

        return $next($report);
    }
}

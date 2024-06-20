<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Report;
use Spatie\LaravelFlare\Performance\Support\Telemetry;

class AddNotifierName implements FlareMiddleware
{
    public function handle(Report $report, $next)
    {
        $report->notifierName(Telemetry::NAME);

        return $next($report);
    }
}

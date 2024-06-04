<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Report;

class AddNotifierName implements FlareMiddleware
{
    public const NOTIFIER_NAME = 'Laravel Client';

    public function handle(Report $report, $next)
    {
        $report->notifierName(static::NOTIFIER_NAME);

        return $next($report);
    }
}

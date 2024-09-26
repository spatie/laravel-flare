<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Illuminate\Log\Context\Repository;
use Illuminate\Support\Facades\Context;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;

class AddLaravelContext implements FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next): Closure|ReportFactory
    {
        if (! class_exists(Repository::class)) {
            return $next($report);
        }

        $allContext = Context::all();

        if (count($allContext)) {
            $report->addAttribute('context.laravel', $allContext);
        }

        return $next($report);
    }
}

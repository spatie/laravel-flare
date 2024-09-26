<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;
use Spatie\LaravelFlare\AttributesProviders\LaravelAttributesProvider;

class AddLaravelInformation implements FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next): Closure|ReportFactory
    {
        $report->addAttributes(
            (new LaravelAttributesProvider())->toArray()
        );

        return $next($report);
    }
}

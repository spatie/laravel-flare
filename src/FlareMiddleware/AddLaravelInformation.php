<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;
use Spatie\LaravelFlare\AttributesProviders\LaravelAttributesProvider;

class AddLaravelInformation implements FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next)
    {
        $report->frameworkVersion(app()->version());

        $report->setAttributes(
            (new LaravelAttributesProvider())->toArray()
        );

        return $next($report);
    }
}

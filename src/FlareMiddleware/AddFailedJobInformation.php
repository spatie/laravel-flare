<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;

class AddFailedJobInformation implements FlareMiddleware
{
    public function __construct(
    ) {
    }

    public function handle(ReportFactory $report, Closure $next)
    {
        return $next($report);

        // TODO

        $attributes = $this->recorder->getAttributes();

        ray($this->recorder);

        if ($attributes === null) {
            return $next($report);
        }

        $report->addAttributes($attributes);

        return $next($report);
    }
}

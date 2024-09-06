<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Illuminate\Database\QueryException;
use Spatie\FlareClient\Contracts\ProvidesFlareContext;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;

class AddExceptionInformation implements FlareMiddleware
{
    public function handle(ReportFactory $report, $next)
    {
        $this->addUserDefinedContext($report);

        if (! $report->throwable instanceof QueryException) {
            return $next($report);
        }

        // TODO: make sure we parse this correctly within Flare
        $report->addAttribute(
            'flare.exception.db_statement',
            $report->throwable->getSql(),
        );

        return $next($report);
    }

    private function addUserDefinedContext(
        ReportFactory $report,
    ): void {
        if ($report->throwable === null) {
            return;
        }

        if ($report->throwable instanceof ProvidesFlareContext) {
            // ProvidesFlareContext writes directly to context groups and is handled in the flare-client-php package.
            return;
        }

        if (! method_exists($report->throwable, 'context')) {
            return;
        }

        $context = $report->throwable->context();

        if (! is_array($context)) {
            return;
        }

        $report->context($context);
    }
}

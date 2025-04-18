<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\FlareClient\Contracts\ProvidesFlareContext;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;
use Throwable;

class AddExceptionInformation implements FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        $this->addUserDefinedContext($report);

        if (! $report->throwable instanceof QueryException) {
            return $next($report);
        }

        $report->addAttribute(
            'flare.exception.db_statement',
            $report->throwable->getSql(),
        );

        try {
            $report->addAttribute(
                'flare.exception.db_system',
                DB::connection($report->throwable->connectionName)->getDriverName(),
            );
        } catch (Throwable) {
            // Skip this
        }

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

        $report->addAttribute('context.exception', $context);
    }
}

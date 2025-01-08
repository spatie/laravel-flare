<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\FlareClient\Contracts\ProvidesFlareContext;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Report;
use Throwable;

class AddExceptionInformation implements FlareMiddleware
{
    public function handle(Report $report, $next)
    {
        $throwable = $report->getThrowable();

        $this->addUserDefinedContext($report);

        if (! $throwable instanceof QueryException) {
            return $next($report);
        }

        $report->group('exception', [
            'raw_sql' => $throwable->getSql(),
        ]);

        try {
            $report->group('exception', [
                'db_system' => DB::connection($throwable->connectionName)->getDriverName(),
            ]);
        } catch (Throwable) {
            // Skip this
        }

        return $next($report);
    }

    private function addUserDefinedContext(Report $report): void
    {
        $throwable = $report->getThrowable();

        if ($throwable === null) {
            return;
        }

        if ($throwable instanceof ProvidesFlareContext) {
            // ProvidesFlareContext writes directly to context groups and is handled in the flare-client-php package.
            return;
        }

        if (! method_exists($throwable, 'context')) {
            return;
        }

        $context = $throwable->context();

        if (! is_array($context)) {
            return;
        }

        $exceptionContextGroup = [];
        foreach ($context as $key => $value) {
            $exceptionContextGroup[$key] = $value;
        }
        $report->group('exception', $exceptionContextGroup);
    }
}

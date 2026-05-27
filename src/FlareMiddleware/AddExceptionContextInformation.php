<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\FlareClient\Contracts\ProvidesFlareContext;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;
use Throwable;

class AddExceptionContextInformation implements FlareMiddleware
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

        $this->rewriteQueryExceptionMessage($report);

        return $next($report);
    }

    private function rewriteQueryExceptionMessage(ReportFactory $report): void
    {
        if ($report->message === null) {
            return;
        }

        if (! preg_match('/^(.*, SQL: ).+\)$/s', $report->message, $matches)) {
            return;
        }

        /** @var QueryException $throwable */
        $throwable = $report->throwable;

        $bindings = array_map(fn ($value) => match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default => "'".str_replace("'", "''", (string) $value)."'",
        }, $throwable->getBindings());

        $sql = Str::replaceArray('?', $bindings, $throwable->getSql());

        $report->message = $matches[1].$sql.')';
    }

    private function addUserDefinedContext(
        ReportFactory $report,
    ): void {
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

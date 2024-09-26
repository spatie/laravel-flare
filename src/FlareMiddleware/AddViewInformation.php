<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Spatie\Backtrace\Arguments\ReduceArgumentPayloadAction;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;
use Spatie\LaravelFlare\Exceptions\ViewException;
use Spatie\LaravelFlare\Exceptions\ViewExceptionWithSolution;

class AddViewInformation implements FlareMiddleware
{
    public function __construct(
        protected ReduceArgumentPayloadAction $reduceArgumentPayloadAction
    ) {
    }

    public function handle(ReportFactory $report, Closure $next): Closure|ReportFactory
    {
        if (! $report->throwable instanceof ViewException) {
            return $next($report);
        }

        $viewException = $report->throwable;

        if ($previous = $report->throwable->getPrevious()) {
            $report->throwable = $previous;
        }

        $report->addAttributes([
            'view.file' => $viewException->getViewFile(),
            'view.data' => collect($viewException->getViewData())->map(
                fn (mixed $value) => $this->reduceArgumentPayloadAction->reduce($value)->value
            )->all(),
        ]);

        if ($viewException instanceof ViewExceptionWithSolution) {
            $report->addSolutions($viewException->getSolution());
        }

        return $next($report);
    }
}

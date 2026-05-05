<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Spatie\Backtrace\Arguments\ReduceArgumentPayloadAction;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Recorders\DumpRecorder\HtmlDumper;
use Spatie\FlareClient\ReportFactory;
use Spatie\LaravelFlare\Exceptions\ViewException;

class AddViewInformation implements FlareMiddleware
{
    public function __construct(
        protected ReduceArgumentPayloadAction $reduceArgumentPayloadAction
    ) {
    }

    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        if (! $report->throwable instanceof ViewException) {
            return $next($report);
        }

        $viewException = $report->throwable;

        if ($previous = $report->throwable->getPrevious()) {
            $report->throwable = $previous;
        }

        $report->addAttributes([
            'view.file' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $viewException->getViewFile()),
            'view.data' => collect($viewException->getViewData())->map(
                fn (mixed $value) => (new HtmlDumper())->dumpVariable($value)
            )->all(),
        ]);

        return $next($report);
    }
}

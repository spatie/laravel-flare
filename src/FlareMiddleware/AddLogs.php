<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Report;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogRecorder;

class AddLogs implements FlareMiddleware
{
    protected LogRecorder $logRecorder;

    public function __construct()
    {
        $this->logRecorder = app(LogRecorder::class);
    }

    public function handle(Report $report, $next)
    {
        $report->group('logs', $this->logRecorder->getLogMessages());

        return $next($report);
    }
}

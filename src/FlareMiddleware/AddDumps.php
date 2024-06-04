<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Report;
use Spatie\LaravelFlare\Recorders\DumpRecorder\DumpRecorder;

class AddDumps implements FlareMiddleware
{
    protected DumpRecorder $dumpRecorder;

    public function __construct()
    {
        $this->dumpRecorder = app(DumpRecorder::class);
    }

    public function handle(Report $report, Closure $next)
    {
        $report->group('dumps', $this->dumpRecorder->getDumps());

        return $next($report);
    }
}

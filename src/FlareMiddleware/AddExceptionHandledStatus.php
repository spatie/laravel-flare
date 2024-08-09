<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Support\BackTracer;
use Throwable;

class AddExceptionHandledStatus implements FlareMiddleware
{
    public function __construct(
        protected BackTracer $backTracer
    ) {
    }

    public function handle(ReportFactory $report, Closure $next)
    {
        $frames = $this->backTracer->frames(40);
        $frameCount = count($frames);

        try {
            foreach ($frames as $i => $frame) {
                // Check first frame, probably Illuminate\Foundation\Exceptions\Handler::report()
                // Next frame should be: Illuminate/Foundation/helpers.php::report()

                if ($frame->method !== 'report') {
                    continue;
                }

                if ($frame->class === null) {
                    continue;
                }

                if ($i === $frameCount - 1) {
                    continue;
                }

                if ($frames[$i + 1]->class !== null) {
                    continue;
                }

                if ($frames[$i + 1]->method !== 'report') {
                    continue;
                }

                $report->handled();

                break;
            }
        } catch (Throwable) {
            // Do nothing
        }

        return $next($report);
    }
}

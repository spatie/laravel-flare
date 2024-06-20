<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\FlareMiddleware\ContainerAwareFlareMiddleware;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\FlareMiddleware\RecordingMiddleware;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Support\Container;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogRecorder;

class AddLogs implements FlareMiddleware, RecordingMiddleware
{
    protected LogRecorder $logRecorder;

    public function __construct(
        protected ?int $maxLogs = 200,
        protected bool $traceLogs = false,
    ) {
    }

    public function handle(Report $report, $next)
    {
        $report->group('logs', $this->logRecorder->getLogMessages());

        return $next($report);
    }

    public function setupRecording(Closure $setup,): void
    {
        $setup(
            LogRecorder::class,
            fn(ContainerInterface $container) => new LogRecorder(
                app(),
                $container->get(Tracer::class),
                $this->maxLogs,
                $this->traceLogs,
            ),
            fn(LogRecorder $recorder) => $this->logRecorder = $recorder,
        );
    }

    public function getRecorder(): Recorder
    {
        return $this->logRecorder;
    }
}

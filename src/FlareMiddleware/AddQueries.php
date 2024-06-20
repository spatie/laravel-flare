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
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder;

class AddQueries implements FlareMiddleware, RecordingMiddleware
{
    protected QueryRecorder $queryRecorder;

    public function __construct(
        protected bool $reportBindings = true,
        protected ?int $maxQueries = 200,
        protected ?int $traceQueryOriginThreshold = 300,
    ) {
    }

    public function handle(Report $report, $next)
    {
        $report->group('queries', $this->queryRecorder->getQueries());

        return $next($report);
    }

    public function setupRecording(Closure $setup,): void
    {
        $setup(
            QueryRecorder::class,
            fn(ContainerInterface $container) => new QueryRecorder(
                app(),
                $container->get(Tracer::class),
                $this->reportBindings,
                $this->maxQueries,
                $this->traceQueryOriginThreshold,
            ),
            fn(QueryRecorder $recorder) => $this->queryRecorder = $recorder,
        );
    }

    public function getRecorder(): Recorder
    {
        return $this->queryRecorder;
    }
}

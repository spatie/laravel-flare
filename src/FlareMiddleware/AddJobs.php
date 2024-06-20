<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\FlareMiddleware\ContainerAwareFlareMiddleware;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\FlareMiddleware\RecordingMiddleware;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Support\Container;
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;

class AddJobs implements FlareMiddleware, RecordingMiddleware
{
    protected JobRecorder $jobRecorder;

    public function __construct(protected int $maxChainedJobReportingDepth = 5)
    {
    }

    public function handle(Report $report, $next)
    {
        if ($job = $this->jobRecorder->getJob()) {
            $report->group('job', $job);
        }

        return $next($report);
    }

    public function setupRecording(Closure $setup,): void
    {
        $setup(
            JobRecorder::class,
            fn(ContainerInterface $container) => new JobRecorder(
                app(),
                $this->maxChainedJobReportingDepth,
            ),
            fn(JobRecorder $recorder) => $this->jobRecorder = $recorder,
        );
    }

    public function getRecorder(): Recorder
    {
        return $this->jobRecorder;
    }
}

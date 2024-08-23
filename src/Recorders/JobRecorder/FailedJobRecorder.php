<?php

namespace Spatie\LaravelFlare\Recorders\JobRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobPopped;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Events\JobRetryRequested;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Queue\Events\WorkerStopping;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\LaravelFlare\AttributesProviders\LaravelJobAttributesProvider;

class FailedJobRecorder implements Recorder
{
    protected int $maxChainedJobReportingDepth = 3;

    protected ?array $attributes = null;

    public static function type(): string|RecorderType
    {
        return 'failed_job';
    }

    public function __construct(
        protected Dispatcher $dispatcher,
        protected ArgumentReducers|null $argumentReducers,
        array $config,
    ) {
        $this->configure($config);
    }

    protected function configure(array $config): void
    {
        $this->maxChainedJobReportingDepth = $config['maxChainedJobReportingDepth'] ?? 3;
    }

    public function start(): void
    {
        $this->dispatcher->listen(JobExceptionOccurred::class, [$this, 'record']);
        $this->dispatcher->listen(JobFailed::class, fn($e) => ray($e));
//        $this->dispatcher->listen(JobPopped::class, fn($e) => ray($e));
//        $this->dispatcher->listen(JobPopping::class, fn($e) => ray($e));
        $this->dispatcher->listen(JobProcessed::class, fn($e) => ray($e));
        $this->dispatcher->listen(JobProcessing::class, fn($e) => ray($e));
        $this->dispatcher->listen(JobQueued::class, fn($e) => ray($e));
        $this->dispatcher->listen(JobQueueing::class, fn($e) => ray($e));
        $this->dispatcher->listen(JobReleasedAfterException::class, fn($e) => ray($e));
        $this->dispatcher->listen(JobRetryRequested::class, fn($e) => ray($e));
        $this->dispatcher->listen(JobTimedOut::class, fn($e) => ray($e));
//        $this->dispatcher->listen(Looping::class, fn($e) => ray($e));
        $this->dispatcher->listen(QueueBusy::class, fn($e) => ray($e));
        $this->dispatcher->listen(WorkerStopping::class, fn($e) => ray($e));
    }

    public function record(JobExceptionOccurred $event): void
    {
        $this->attributes = (new LaravelJobAttributesProvider($this->maxChainedJobReportingDepth, $this->argumentReducers))->toArray(
            $event->job
        );

        ray($this->attributes);
    }

    public function reset(): void
    {
        $this->attributes = null;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }
}

<?php

namespace Spatie\LaravelFlare\Recorders\JobRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
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

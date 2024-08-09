<?php

namespace Spatie\LaravelFlare\Recorders\QueueRecorder;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;

class QueueRecorder implements SpansRecorder
{
    use RecordsPendingSpans;

    public static function type(): RecorderType
    {
        return RecorderType::Queue;
    }

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        array $config
    ) {
        $this->configure($config);
    }
}

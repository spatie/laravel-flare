<?php

namespace Spatie\LaravelFlare\Recorders\CacheRecorder;

use Closure;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder as BaseCacheRecorder;
use Spatie\FlareClient\Tracer;
use Spatie\FlareClient\Support\BackTracer;

class CacheRecorder extends BaseCacheRecorder
{
    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        array $config
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public function start(): void
    {
        $this->dispatcher->listen(CacheHit::class, fn (CacheHit $event) => $this->recordHit(
            $event->key,
            $event->storeName,
        ));
        $this->dispatcher->listen(CacheMissed::class, fn (CacheMissed $event) => $this->recordMiss(
            $event->key,
            $event->storeName,
        ));
        $this->dispatcher->listen(KeyWritten::class, fn (KeyWritten $event) => $this->recordKeyWritten(
            $event->key,
            $event->storeName,
        ));
        $this->dispatcher->listen(KeyForgotten::class, fn (KeyForgotten $event) => $this->recordKeyForgotten(
            $event->key,
            $event->storeName,
        ));
    }
}

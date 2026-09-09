<?php

namespace Spatie\LaravelFlare\Recorders\CacheRecorder;

use Illuminate\Cache\Events\CacheFailedOver;
use Illuminate\Cache\Events\CacheFlushed;
use Illuminate\Cache\Events\CacheFlushFailed;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheLocksFlushed;
use Illuminate\Cache\Events\CacheLocksFlushFailed;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgetFailed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWriteFailed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Contracts\Events\Dispatcher;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder as BaseCacheRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;

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

    public function boot(): void
    {
        $this->dispatcher->listen(CacheHit::class, fn (CacheHit $event) => $this->recordHit(
            $event->key,
            $event->storeName ?? null,
        ));

        $this->dispatcher->listen(CacheMissed::class, fn (CacheMissed $event) => $this->recordMiss(
            $event->key,
            $event->storeName ?? null,
        ));

        $this->dispatcher->listen(KeyWritten::class, fn (KeyWritten $event) => $this->recordKeyWritten(
            $event->key,
            $event->storeName ?? null,
        ));

        $this->dispatcher->listen(KeyForgotten::class, fn (KeyForgotten $event) => $this->recordKeyForgotten(
            $event->key,
            $event->storeName ?? null,
        ));

        if (class_exists(KeyWriteFailed::class)) {
            $this->dispatcher->listen(KeyWriteFailed::class, fn (KeyWriteFailed $event) => $this->recordKeyWriteFailed(
                $event->key,
                $event->storeName ?? null,
            ));
        }

        if (class_exists(KeyForgetFailed::class)) {
            $this->dispatcher->listen(KeyForgetFailed::class, fn (KeyForgetFailed $event) => $this->recordKeyForgetFailed(
                $event->key,
                $event->storeName ?? null,
            ));
        }

        if (class_exists(CacheFailedOver::class)) {
            $this->dispatcher->listen(CacheFailedOver::class, fn (CacheFailedOver $event) => $this->recordFailedOver(
                $event->storeName,
                $event->exception,
            ));
        }

        if (class_exists(CacheFlushed::class)) {
            $this->dispatcher->listen(CacheFlushed::class, fn (CacheFlushed $event) => $this->recordFlushed(
                $event->storeName,
            ));
        }

        if (class_exists(CacheFlushFailed::class)) {
            $this->dispatcher->listen(CacheFlushFailed::class, fn (CacheFlushFailed $event) => $this->recordFlushFailed(
                $event->storeName,
            ));
        }

        if (class_exists(CacheLocksFlushed::class)) {
            $this->dispatcher->listen(CacheLocksFlushed::class, fn (CacheLocksFlushed $event) => $this->recordLocksFlushed(
                $event->storeName,
            ));
        }

        if (class_exists(CacheLocksFlushFailed::class)) {
            $this->dispatcher->listen(CacheLocksFlushFailed::class, fn (CacheLocksFlushFailed $event) => $this->recordLocksFlushFailed(
                $event->storeName,
            ));
        }
    }

    /** @return array<int, string> */
    protected function defaultIgnoredKeys(): array
    {
        return [
            '/^illuminate:(?!cache:flexible:created:)/',
            '/^framework\/schedule/',
            '/^laravel_vapor_job_attempt?s:/',
            '/^laravel:pulse:/',
            '/^laravel:reverb:/',
            '/^laravel:horizon:/',
            '/^horizon:/',
            '/^nova/',
            '/^telescope:/',
            '/^livewire-checksum-failures:/',
        ];
    }
}

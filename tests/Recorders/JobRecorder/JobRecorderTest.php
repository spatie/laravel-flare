<?php

use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Tests\Shared\ExpectSpan;
use Spatie\FlareClient\Tests\Shared\ExpectSpanEvent;
use Spatie\FlareClient\Tests\Shared\ExpectTrace;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;

uses(ConfigureFlare::class);

it('can trace jobs executions', function () {
    setupFlareForTracing();

    try {
        dispatch(function () {
            return 'ok';
        })->onConnection('sync');
    } catch (\Exception $e) {
        $this->assertNotNull($e);
    }

    ExpectTrace::create(FakeSender::instance()->getLastPayload())
        ->hasSpanCount(1)
        ->span(
            fn (ExpectSpan $span) => $span
                ->hasName('Job - Closure (JobRecorderTest.php:'.__LINE__ - 11 .')')
                ->hasAttribute('flare.span_type', SpanType::Job)
                ->hasAttribute('laravel.job.queue.connection_name', 'sync')
                ->hasAttribute('laravel.job.queue.name', 'sync')
                ->hasAttribute('laravel.job.success', true)
                ->hasAttribute('laravel.job.delete_when_missing_models', true)
                ->hasSpanEventCount(0)
        );
});

it('can trace jobs failures', function () {
    setupFlareForTracing();

    try {
        dispatch(function () {
            throw new \Exception('Failed');
        })->onConnection('sync');
    } catch (\Exception $e) {
        $this->assertNotNull($e);
    }

    ExpectTrace::create(FakeSender::instance()->getLastPayload())
        ->hasSpanCount(1)
        ->span(
            fn (ExpectSpan $span) => $span
                ->hasAttribute('laravel.job.success', false)
                ->hasSpanEventCount(1)
                ->spanEvent(
                    fn (ExpectSpanEvent $spanEvent) => $spanEvent
                        ->hasName('Exception - Exception')
                        ->hasAttribute('flare.span_event_type', SpanEventType::Exception)
                        ->hasAttribute('exception.message', 'Failed')
                )
        );
});

it('will not try to add an exception to a never started span', function () {
    setupFlareForTracing(
        fn (FlareConfig $config) => $config->sampleRate(0)
    );

    try {
        dispatch(function () {
            throw new \Exception('Failed');
        })->onConnection('sync');
    } catch (\Exception $e) {
        $this->assertNotNull($e);
    }

    $this->assertNull(FakeSender::instance()->getLastPayload());
});

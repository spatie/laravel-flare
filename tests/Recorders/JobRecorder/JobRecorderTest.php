<?php

use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\LaravelFlare\Enums\SpanType;

it('can trace jobs executions', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    try {
        dispatch(function () {
            return 'ok';
        })->onConnection('sync');
    } catch (Exception $e) {
        $this->assertNotNull($e);
    }

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectType(SpanType::Job)
        ->expectName('Job - Closure (JobRecorderTest.php:'.__LINE__ - 13 .')')
        ->expectAttribute('laravel.job.queue.connection_name', 'sync')
        ->expectAttribute('laravel.job.queue.name', 'sync')
        ->expectAttribute('laravel.job.success', true)
        ->expectAttribute('laravel.job.delete_when_missing_models', true)
        ->expectHasAttribute('flare.peak_memory_usage')
        ->expectSpanEventCount(0);
});

it('can trace jobs failures', function () {
    $flare = setupFlare(alwaysSampleTraces: true, isUsingSubtasks: true);

    $flare->tracer->startTrace();

    try {
        dispatch(function () {
            throw new Exception('Failed');
        })->onConnection('sync');
    } catch (Exception $e) {
        $this->assertNotNull($e);
    }

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectType(SpanType::Job)
        ->expectAttribute('laravel.job.success', false)
        ->expectSpanEventCount(1)
        ->expectSpanEvent(0)
        ->expectName('Exception - Exception')
        ->expectType(SpanEventType::Exception)
        ->expectAttribute('exception.message', 'Failed');
});

it('can trace and at the same time report job exceptions', function () {
    FakeIds::setup()->nextUuid('fake-uuid');

    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    dispatch(function () {
        app(Flare::class)->reportHandled(new Exception('Failed'));
    })->onConnection('sync');

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectSpanEvent(0)
        ->expectAttribute('exception.id', 'fake-uuid');

    FakeApi::lastReport()->expectTrackingUuid('fake-uuid');
});

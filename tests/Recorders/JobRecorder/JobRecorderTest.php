<?php

use Illuminate\Container\Container;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Event;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Tests\Shared\ExpectSpan;
use Spatie\FlareClient\Tests\Shared\ExpectSpanEvent;
use Spatie\FlareClient\Tests\Shared\ExpectTrace;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;
use Spatie\LaravelFlare\Tests\stubs\Jobs\QueueableJob;

uses(ConfigureFlare::class);

it('can trace jobs executions', function () {
    setupFlareForTracing();

    try {
        dispatch(function () {
            return 'ok';
        })->onConnection('sync');
    } catch (Exception $e) {
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
            throw new Exception('Failed');
        })->onConnection('sync');
    } catch (Exception $e) {
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
            throw new Exception('Failed');
        })->onConnection('sync');
    } catch (Exception $e) {
        $this->assertNotNull($e);
    }

    $this->assertNull(FakeSender::instance()->getLastPayload());
});

it('can record a closure job', function () {
    $recorder = (new JobRecorder(app()));

    $job = function () {
        throw new \Exception('Die');
    };

    $recorder->record(createEvent(function () use ($job) {
        dispatch($job);
    }));

    $recorded = $recorder->getJob();

    expect($recorded['name'])->toEqual('Closure (JobRecorderTest.php:82)');
});

it('can record a chained job', function () {
    $recorder = (new JobRecorder(app()));

    $recorder->record(createEvent(function () {
        dispatch(new QueueableJob(['level-one']))->chain([
            new QueueableJob(['level-two-a']),
            (new QueueableJob(['level-two-b']))->chain([
                (new QueueableJob(['level-three'])),
            ]),
        ]);
    }));

    $recorded = $recorder->getJob();

    expect($chained = $recorded['data']['chained'])->toHaveCount(2);

    expect($chained[0]['name'])->toEqual(QueueableJob::class);
    expect($chained[0]['data']['property'])->toEqual(['level-two-a']);
    expect($chained[1]['name'])->toEqual(QueueableJob::class);
    expect($chained[1]['data']['property'])->toEqual(['level-two-b']);

    expect($chained = $chained[1]['data']['chained'])->toHaveCount(1);

    expect($chained[0]['name'])->toEqual(QueueableJob::class);
    expect($chained[0]['data']['property'])->toEqual(['level-three']);
});

it('can restrict the recorded chained jobs depth', function () {
    $recorder = (new JobRecorder(app(), 1));

    $recorder->record(createEvent(function () {
        dispatch(new QueueableJob(['level-one']))->chain([
            new QueueableJob(['level-two-a']),
            (new QueueableJob(['level-two-b']))->chain([
                (new QueueableJob(['level-three'])),
            ]),
        ]);
    }));

    $recorded = $recorder->getJob();

    expect($chained = $recorded['data']['chained'])->toHaveCount(2);

    expect($chained[0]['name'])->toEqual(QueueableJob::class);
    expect($chained[0]['data']['property'])->toEqual(['level-two-a']);
    expect($chained[1]['name'])->toEqual(QueueableJob::class);
    expect($chained[1]['data']['property'])->toEqual(['level-two-b']);

    expect($chained = $chained[1]['data']['chained'])->toHaveCount(1);
    expect($chained)->toEqual(['Flare stopped recording jobs after this point since the max chain depth was reached']);
});

it('can disable recording chained jobs', function () {
    $recorder = (new JobRecorder(app(), 0));

    $recorder->record(createEvent(function () {
        dispatch(new QueueableJob(['level-one']))->chain([
            new QueueableJob(['level-two-a']),
            (new QueueableJob(['level-two-b']))->chain([
                (new QueueableJob(['level-three'])),
            ]),
        ]);
    }));

    $recorded = $recorder->getJob();

    expect($chained = $recorded['data']['chained'])->toHaveCount(1);
    expect($chained)->toEqual(['Flare stopped recording jobs after this point since the max chain depth was reached']);
});

it('can handle a job with an unserializeable payload', function () {
    $recorder = (new JobRecorder(app()));

    $payload = json_encode([
        'job' => 'Fake Job Name',
    ]);

    $event = new JobExceptionOccurred(
        'redis',
        new RedisJob(
            app(Container::class),
            app(RedisQueue::class),
            $payload,
            $payload,
            'redis',
            'default'
        ),
        new Exception()
    );

    $recorder->record($event);

    $recorded = $recorder->getJob();

    expect($recorded['name'])->toEqual('Fake Job Name');
    expect($recorded['connection'])->toEqual('redis');
    expect($recorded['queue'])->toEqual('default');
});

// Helpers
function createEvent(Closure $dispatch): JobExceptionOccurred
{
    $triggeredEvent = null;

    Event::listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event) use (&$triggeredEvent) {
        $triggeredEvent = $event;
    });

    try {
        $dispatch();
    } catch (Exception $exception) {
    }

    if ($triggeredEvent === null) {
        throw new Exception("Could not create test event");
    }

    return $triggeredEvent;
}


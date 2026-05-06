<?php

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Queue\RedisQueue;
use Illuminate\Queue\SyncQueue;
use function Livewire\invade;
use Spatie\Backtrace\Arguments\ReduceArgumentPayloadAction;
use Spatie\LaravelFlare\AttributesProviders\LaravelJobAttributesProvider;
use Spatie\LaravelFlare\Tests\stubs\Jobs\QueueableJob;

function jobAttributes(Job|ShouldQueue $job, ?string $connectionName = null, int $maxChainedJobReportingDepth = 3): array
{
    if ($job instanceof ShouldQueue) {
        $queue = invade(new SyncQueue());
        $queue->setContainer(app());

        $job = $queue->resolveJob($queue->createPayload($job, null, []), null);
    }

    return (new LaravelJobAttributesProvider(
        app(ReduceArgumentPayloadAction::class),
        $job,
        $connectionName,
        $maxChainedJobReportingDepth,
    ))->toArray();
}

it('can provide attributes for a job', function () {
    setupFlare();

    $attributes = jobAttributes(new QueueableJob([]));

    expect($attributes)
        ->toHaveCount(6)
        ->toHaveKey('laravel.job.name', QueueableJob::class)
        ->toHaveKey('laravel.job.class', QueueableJob::class)
        ->toHaveKey('laravel.job.uuid')
        ->toHaveKey('laravel.job.queue.name', 'sync')
        ->toHaveKey('laravel.job.queue.connection_name', null)
        ->toHaveKey('laravel.job.properties');
});

it('can set the connection name from the outside', function () {
    setupFlare();

    $attributes = jobAttributes(new QueueableJob([]), 'sync');

    expect($attributes)
        ->toHaveCount(6)
        ->toHaveKey('laravel.job.queue.connection_name', 'sync');
});

it('can provide attributes for a job with properties', function () {
    setupFlare();

    $attributes = jobAttributes(new QueueableJob([
        'int' => 42,
        'boolean' => true,
    ]));

    expect($attributes['laravel.job.properties']['property'])
        ->toHaveCount(2)
        ->toHaveKey('int', 42)
        ->toHaveKey('boolean', true);
});

it('can provide attributes for a job with properties which values will be reduced', function () {
    setupFlare();

    $attributes = jobAttributes(new QueueableJob([
        'object' => new stdClass(),
    ]));

    expect($attributes['laravel.job.properties']['property'])
        ->toHaveKey('object', 'object (stdClass)');
});

it('can parse job properties set by the user', function () {
    setupFlare();

    $date = CarbonImmutable::create(2020, 05, 16, 12, 0, 0);

    $job = new QueueableJob(
        property: [],
        retryUntilValue: $date,
        tries: 5,
        maxExceptions: 10,
        timeout: 120
    );

    $attributes = jobAttributes($job);

    expect($attributes['laravel.job.max_tries'])->toEqual(5);
    expect($attributes['laravel.job.max_exceptions'])->toEqual(10);
    expect($attributes['laravel.job.timeout'])->toEqual(120);
    expect($attributes['laravel.job.retry_until'])->toEqual(1589630400000000000);
});

it('can record a closure job', function () {
    setupFlare();

    $attributes = jobAttributes(CallQueuedClosure::create(function () {
        return 'Hello, World!';
    }));

    expect($attributes['laravel.job.class'])->toEqual(CallQueuedClosure::class);
    expect($attributes['laravel.job.name'])->toEqual('Closure (LaravelJobAttributesProviderTest.php:'.(__LINE__ - 5).')');
});

it('can provide attributes for chained jobs', function () {
    setupFlare();

    $attributes = jobAttributes(
        (new QueueableJob(['level-one']))->chain([
            new QueueableJob(['level-two-a']),
            (new QueueableJob(['level-two-b']))->chain([
                (new QueueableJob(['level-three'])),
            ]),
        ])
    );

    $chain = $attributes['laravel.job.chain.jobs'];

    expect($chain)->toHaveCount(2);

    expect($chain[0])
        ->toHaveCount(2)
        ->toHaveKey('laravel.job.class', QueueableJob::class)
        ->toHaveKey('laravel.job.properties', [
            'property' => ['level-two-a'],
        ]);

    expect($chain[1])
        ->toHaveCount(3)
        ->toHaveKey('laravel.job.class', QueueableJob::class)
        ->toHaveKey('laravel.job.properties', [
            'property' => ['level-two-b'],
        ])
        ->toHaveKey('laravel.job.chain.jobs');

    $nestedChain = $chain[1]['laravel.job.chain.jobs'];

    expect($nestedChain)->toHaveCount(1);

    expect($nestedChain[0])
        ->toHaveCount(2)
        ->toHaveKey('laravel.job.class', QueueableJob::class)
        ->toHaveKey('laravel.job.properties', [
            'property' => ['level-three'],
        ]);
});

it('can restrict the chain depth', function () {
    setupFlare();

    $attributes = jobAttributes(
        (new QueueableJob(['level-one']))->chain([
            (new QueueableJob(['level-two-b']))->chain([
                (new QueueableJob(['level-three'])),
            ]),
        ]),
        maxChainedJobReportingDepth: 1
    );

    $chain = $attributes['laravel.job.chain.jobs'];

    expect($chain)->toHaveCount(1);
    expect($chain[0])->not()->toHaveKey('laravel.job.chain.jobs');
});

it('can disable including the chain', function () {
    setupFlare();

    $attributes = jobAttributes(
        (new QueueableJob(['level-one']))->chain([
            (new QueueableJob(['level-two-b']))->chain([
                (new QueueableJob(['level-three'])),
            ]),
        ]),
        maxChainedJobReportingDepth: 0
    );

    expect($attributes)->not()->toHaveKey('laravel.job.chain.jobs');
});

it('can handle a job with an unserializeable payload', function () {
    setupFlare();

    $payload = json_encode([
        'job' => 'Fake Job Name',
    ]);

    $job = new RedisJob(
        app(Container::class),
        app(RedisQueue::class),
        $payload,
        $payload,
        'redis',
        'default'
    );

    $attributes = jobAttributes($job);

    expect($attributes['laravel.job.queue.connection_name'])->toEqual('redis');
    expect($attributes['laravel.job.queue.name'])->toEqual('default');
});

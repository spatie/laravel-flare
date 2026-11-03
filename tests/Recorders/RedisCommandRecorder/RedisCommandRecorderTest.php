<?php

use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Events\CommandExecuted;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\LaravelFlare\FlareConfig;

it('skips unknown command durations but records measured zero durations', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectRedisCommands(), alwaysSampleTraces: true);
    $connection = new PhpRedisConnection(null);
    $flare->tracer->startTrace();

    event(new CommandExecuted('get', ['unknown'], null, $connection));
    event(new CommandExecuted('get', ['measured'], 0.0, $connection));

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(SpanType::RedisCommand)
        ->expectAttribute('db.query.parameters', ['measured'])
        ->expectEnded();
});

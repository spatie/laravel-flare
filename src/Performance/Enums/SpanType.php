<?php

namespace Spatie\LaravelFlare\Performance\Enums;

use Spatie\FlareClient\Contracts\FlareSpanType;

enum SpanType : string implements FlareSpanType
{
    case Request = 'laravel_request';
    case Job = 'laravel_job';
    case Command = 'laravel_command';
    case Transaction= 'laravel_transaction';
    case Query = 'laravel_query';
    case RedisCommand = 'laravel_redis_command';
}

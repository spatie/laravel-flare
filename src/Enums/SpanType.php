<?php

namespace Spatie\LaravelFlare\Enums;

use Spatie\FlareClient\Contracts\FlareSpanType;

enum SpanType : string implements FlareSpanType
{
    case Request = 'laravel_request';
    case Routing = 'laravel_routing';
    case Job = 'laravel_job';
    case Query = 'laravel_query';
    case RedisCommand = 'laravel_redis_command';
    case Application = 'laravel_application';
    case Booting = 'laravel_booting';

    public function humanReadable(): string
    {
        return match ($this) {
            self::Request => 'request',
            self::Job => 'job',
            self::Query => 'query',
            self::RedisCommand => 'redis command',
            self::Application => 'application',
        };
    }
}

<?php

namespace Spatie\LaravelFlare\Enums;

use Spatie\FlareClient\Contracts\FlareSpanType;

enum SpanType: string implements FlareSpanType
{
    case Routing = 'laravel_routing';
    case GlobalMiddlewareBefore = 'laravel_global_middleware_before';
    case LocalMiddlewareBefore = 'laravel_local_middleware_before';
    case Response = 'laravel_response';
    case Queueing = 'laravel_queueing';
    case Job = 'laravel_job';
    case Notification = 'laravel_notification';
}

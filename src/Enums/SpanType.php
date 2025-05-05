<?php

namespace Spatie\LaravelFlare\Enums;

use Spatie\FlareClient\Contracts\FlareSpanType;

enum SpanType: string implements FlareSpanType
{
    case Routing = 'laravel_routing';
    case Application = 'laravel_application';
    case Boot = 'laravel_boot';
    case Registration = 'laravel_registration';
    case GlobalMiddlewareBefore = 'laravel_global_middleware_before';
    case LocalMiddlewareBefore = 'laravel_local_middleware_before';
    case Response = 'laravel_response';
    case Terminating = 'laravel_terminating';
    case Queueing = 'laravel_queueing';
    case Job = 'laravel_job';
    case Notification = 'laravel_notification';
}

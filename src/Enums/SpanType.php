<?php

namespace Spatie\LaravelFlare\Enums;

use Spatie\FlareClient\Contracts\FlareSpanType;

enum SpanType: string implements FlareSpanType
{
    case Routing = 'laravel_routing';
    case Query = 'laravel_query';
    case Application = 'laravel_application';
    case Boot = 'laravel_boot';
    case Registration = 'laravel_registration';
    case GlobalMiddlewareBefore = 'laravel_global_middleware_before';
    case LocalMiddlewareBefore = 'laravel_local_middleware_before';
    case Response = 'laravel_response';
    case Terminating = 'laravel_terminating';
    case Filesystem = 'laravel_filesystem';
}

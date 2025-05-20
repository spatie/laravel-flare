<?php

namespace Spatie\LaravelFlare\Enums;

use Spatie\FlareClient\Contracts\FlareSpanType;

enum SpanType: string implements FlareSpanType
{
    case Response = 'laravel_response';
    case Queueing = 'laravel_queueing';
    case Job = 'laravel_job';
}

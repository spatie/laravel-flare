<?php

namespace Spatie\LaravelFlare\Performance\Enums;

use Spatie\FlareClient\Contracts\FlareSpanEventType;

enum SpanEventType: string implements FlareSpanEventType
{
    case Log = 'laravel_log';
}

<?php

namespace Spatie\LaravelFlare\Enums;

use Spatie\FlareClient\Contracts\FlareCollectType;

enum LaravelCollectType: string implements FlareCollectType
{
    case LivewireComponents = 'laravel_livewire_components';
    case LaravelInfo = 'laravel_info';
    case LaravelContext = 'laravel_context';
    case ExceptionContext = 'laravel_exception_context';
    case HandledExceptions = 'laravel_handled_exceptions';
}

<?php

namespace Spatie\LaravelFlare\Enums;

enum LaravelRouteActionType: string
{
    case Controller = 'controller';
    case Closure = 'closure';
    case View = 'view';
    case Redirect = 'redirect';
    case Unknown = 'unknown';
}

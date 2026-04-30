<?php

namespace Spatie\LaravelFlare\Enums;

enum LaravelRouteActionType: string
{
    case Controller = 'controller';
    case Closure = 'closure';
    case View = 'view';
    case Redirect = 'redirect';
    case Unknown = 'unknown';

    public function entryPointHandlerType(): ?string
    {
        return match ($this) {
            self::Controller => 'laravel_controller',
            self::Closure => 'laravel_closure',
            self::View => 'laravel_view',
            self::Redirect => 'laravel_redirect',
            self::Unknown => null,
        };
    }
}

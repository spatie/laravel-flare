<?php

namespace Spatie\LaravelFlare\Enums;

enum LivewireComponentPhase: string
{
    case Mounting = 'mounting';
    case Hydrating = 'hydrating';
    case Calling = 'calling';
    case Rendering = 'rendering';
    case Dehydrating = 'dehydrating';

    public static function fromLivewireProfileEvent(string $name): ?self
    {
        if (str_starts_with($name, 'call')) {
            $name = 'call';
        }

        return match ($name) {
            'mount' => self::Mounting,
            'hydrate' => self::Hydrating,
            'call' => self::Calling,
            'render' => self::Rendering,
            'dehydrate' => self::Dehydrating,
            default => null,
        };
    }
}

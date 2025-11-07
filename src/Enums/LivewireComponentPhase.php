<?php

namespace Spatie\LaravelFlare\Enums;

enum LivewireComponentPhase: string
{
    case Mounting = 'mounting';
    case Hydrating = 'hydrating';
    case Calling = 'calling';
    case Rendering = 'rendering';
    case Dehydrating = 'dehydrating';
    case Destroyed = 'destroyed';
}

<?php

namespace Spatie\LaravelFlare\Enums;

use Spatie\FlareClient\Contracts\FlareSpanType;

enum SpanType: string implements FlareSpanType
{
    case Response = 'laravel_response';

    case LivewireComponent = 'laravel_livewire_component';
    case LivewireComponentMounting = 'laravel_livewire_component_mounting';
    case LivewireComponentPreMounting = 'laravel_livewire_component_premounting';
    case LivewireComponentHydrating = 'laravel_livewire_component_hydrating';
    case LivewireComponentCall = 'laravel_livewire_component_call';
    case LivewireComponentRendering = 'laravel_livewire_component_rendering';
    case LivewireComponentDehydrating = 'laravel_livewire_component_dehydrating';
}

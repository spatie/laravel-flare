<?php

namespace Spatie\LaravelFlare\Support;

class LivewireComponentClassFinder
{
    public static function findForComponentName(string $name): ?string
    {
        // Livewire v4
        if (class_exists(\Livewire\Finder\Finder::class)) {
            return app(\Livewire\Finder\Finder::class)->resolveClassComponentClassName($name);
        }


        // Livewire v3
        return app(\Livewire\Mechanisms\ComponentRegistry::class)->getClass($name);
    }
}

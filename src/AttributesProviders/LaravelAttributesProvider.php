<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Spatie\FlareClient\Enums\Framework;

class LaravelAttributesProvider
{
    public function toArray(): array
    {
        return [
            'laravel.locale' => app()->getLocale(),
            'laravel.config_cached' => app()->configurationIsCached(),
            'laravel.debug' => config('app.debug'),
            'flare.framework.name' => Framework::Laravel,
            'flare.framework.version' => app()->version(),
        ];
    }
}

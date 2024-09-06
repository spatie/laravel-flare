<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Spatie\FlareClient\Enums\Framework;

class LaravelAttributesProvider
{
    public function toArray(): array
    {
        return [
            'laravel.version' => app()->version(),
            'laravel.locale' => app()->getLocale(),
            'laravel.config_cached' => app()->configurationIsCached(),
            'laravel.debug' => config('app.debug'),
            'flare.framework' => Framework::Laravel,
        ];
    }
}

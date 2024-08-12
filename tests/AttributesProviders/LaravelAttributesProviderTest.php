<?php

use Spatie\LaravelFlare\AttributesProviders\LaravelAttributesProvider;

it('can provide attributes', function () {
    config()->set('app.debug', true);
    config()->set('app.locale', 'nl');

    $provider = new LaravelAttributesProvider();

    $attributes = $provider->toArray();

    expect($attributes)->toBeArray();
    expect($attributes)->toHaveCount(4);
    expect($attributes)->toHaveKey('laravel.version', app()->version());
    expect($attributes)->toHaveKey('laravel.locale', 'nl');
    expect($attributes)->toHaveKey('laravel.config_cached', false);
    expect($attributes)->toHaveKey('laravel.debug', true);
});

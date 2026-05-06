<?php

use Illuminate\Http\Request;
use Spatie\FlareClient\Support\Redactor;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Spatie\LaravelFlare\Support\LivewireComponentFinder;

it('returns the url', function () {
    $request = Request::create('/route', 'GET');

    $attributes = (new LaravelRequestAttributesProvider(
        new Redactor(),
        app(LivewireComponentFinder::class),
        $request,
    ))->toArray();

    expect($attributes['url.full'])->toBe('http://localhost/route');
});

it('returns the cookies', function () {
    $request = Request::create('/route', 'GET', [], ['cookie' => 'noms']);

    $attributes = (new LaravelRequestAttributesProvider(
        new Redactor(),
        app(LivewireComponentFinder::class),
        $request,
    ))->toArray();

    expect($attributes['http.request.cookies'])->toBe(['cookie' => 'noms']);
});

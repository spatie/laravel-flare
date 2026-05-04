<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelFlare\AttributesProviders\LaravelRouteAttributesProvider;

it('returns the route name', function () {
    $route = Route::get('/route/', fn () => null)->name('routeName');

    $request = Request::create('/route', 'GET');
    $route->bind($request);

    $attributes = (new LaravelRouteAttributesProvider($route, $request->getMethod()))->toArray();

    expect($attributes['laravel.route.name'])->toBe('routeName');
});

it('returns the route parameters', function () {
    $route = Route::get('/route/{parameter}/{otherParameter}', fn () => null);

    $request = Request::create('/route/value/second', 'GET');
    $route->bind($request);

    $attributes = (new LaravelRouteAttributesProvider($route, $request->getMethod()))->toArray();

    expect($attributes['laravel.route.parameters'])->toBe([
        'parameter' => 'value',
        'otherParameter' => 'second',
    ]);
});

it('calls toFlare on a route parameter when it exists', function () {
    $route = Route::get('/route/{user}', fn ($user) => null);

    $request = Request::create('/route/1', 'GET');
    $route->bind($request);
    $route->setParameter('user', new class() {
        public function toFlare(): array
        {
            return ['stripped'];
        }
    });

    $attributes = (new LaravelRouteAttributesProvider($route, $request->getMethod()))->toArray();

    expect($attributes['laravel.route.parameters'])->toBe([
        'user' => ['stripped'],
    ]);
});

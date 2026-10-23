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

it('censors the session and remember me cookies', function () {
    config()->set('session.cookie', 'laravel_session');

    $request = Request::create('/route', 'GET', [], [
        'laravel_session' => 'session-id',
        'remember_web_59ba36addc2b2f9401580f014c7f58ea4e30989d' => 'remember-token',
        'theme' => 'dark',
    ]);

    $attributes = (new LaravelRequestAttributesProvider(
        new Redactor(),
        app(LivewireComponentFinder::class),
        $request,
    ))->toArray();

    expect($attributes['http.request.cookies'])->toBe([
        'laravel_session' => '<CENSORED:string>',
        'remember_web_59ba36addc2b2f9401580f014c7f58ea4e30989d' => '<CENSORED:string>',
        'theme' => 'dark',
    ]);
});

<?php

use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Sampling\DeferredSamplerRule;
use Spatie\FlareClient\Sampling\Rules\PathSamplingRule;
use Spatie\FlareClient\Sampling\Rules\UrlSamplingRule;
use Spatie\FlareClient\Sampling\SamplingRule as BaseSamplingRule;
use Spatie\LaravelFlare\Sampling\RouteActionSamplingRule;
use Spatie\LaravelFlare\Sampling\RouteNameSamplingRule;
use Spatie\LaravelFlare\Sampling\SamplingRule;

it('builds a route-name rule from the fluent factory', function () {
    $rule = SamplingRule::forRouteName('admin.*', 1.0);

    expect($rule)->toBeInstanceOf(RouteNameSamplingRule::class);
});

it('builds a route-action rule from the fluent factory', function () {
    $rule = SamplingRule::forRouteAction('App\\Http\\Controllers\\Admin\\*', 0.5);

    expect($rule)->toBeInstanceOf(RouteActionSamplingRule::class);
});

it('inherits base factories so a single import covers every rule', function () {
    expect(SamplingRule::forUrl('https://example.com/*', 1.0))->toBeInstanceOf(UrlSamplingRule::class)
        ->and(SamplingRule::forPath('/admin/*', 1.0))->toBeInstanceOf(PathSamplingRule::class);
});

it('hydrates an array-form rule using a class FQCN type field', function () {
    $rule = SamplingRule::fromArray([
        'type' => RouteActionSamplingRule::class,
        'pattern' => 'App\\Http\\Controllers\\*',
        'rate' => 1.0,
    ]);

    expect($rule)->toBeInstanceOf(RouteActionSamplingRule::class);
});

it('throws when the array form is missing required keys', function () {
    expect(fn () => SamplingRule::fromArray(['pattern' => 'x', 'rate' => 1.0]))
        ->toThrow(InvalidArgumentException::class, 'Sampling rule array must contain "type", "pattern", and "rate" keys.');
});

it('throws when the type does not resolve to a SamplingRule subclass', function () {
    expect(fn () => SamplingRule::fromArray(['type' => stdClass::class, 'pattern' => 'x', 'rate' => 1.0]))
        ->toThrow(InvalidArgumentException::class, 'Sampling rule "type" must reference a SamplingRule subclass.');
});

it('marks route-name and route-action rules as deferred', function (BaseSamplingRule $rule) {
    expect($rule)->toBeInstanceOf(DeferredSamplerRule::class);
})->with([
    'route name' => fn () => SamplingRule::forRouteName('admin.*', 1.0),
    'route action' => fn () => SamplingRule::forRouteAction('App\\*', 1.0),
]);

it('matches the route name once the handler is resolved', function () {
    $rule = SamplingRule::forRouteName('admin.*', 1.0);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');
    $entryPoint->setHandler(
        handlerIdentifier: 'admin/users',
        handlerName: 'App\\Http\\Controllers\\Admin\\UsersController@index',
        handlerType: 'php_request',
        samplingAttributes: [
            'laravel.route.name' => 'admin.users.index',
            'laravel.route.action' => 'App\\Http\\Controllers\\Admin\\UsersController@index',
        ],
    );

    expect($rule->getMatchedRate($entryPoint))->toBe(1.0);
});

it('returns null when the route name attribute is missing', function () {
    $rule = SamplingRule::forRouteName('admin.*', 1.0);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');
    $entryPoint->setHandler('admin/users', null, 'php_request');

    expect($rule->getMatchedRate($entryPoint))->toBeNull();
});

it('matches the route action against the pattern', function () {
    $rule = SamplingRule::forRouteAction('App\\Http\\Controllers\\Admin\\*', 0.75);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');
    $entryPoint->setHandler(
        handlerIdentifier: 'admin/users',
        handlerName: 'App\\Http\\Controllers\\Admin\\UsersController@index',
        handlerType: 'php_request',
        samplingAttributes: [
            'laravel.route.action' => 'App\\Http\\Controllers\\Admin\\UsersController@index',
        ],
    );

    expect($rule->getMatchedRate($entryPoint))->toBe(0.75);
});

it('does not match unrelated route actions', function () {
    $rule = SamplingRule::forRouteAction('App\\Http\\Controllers\\Admin\\*', 1.0);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/public');
    $entryPoint->setHandler(
        handlerIdentifier: 'public',
        handlerName: 'App\\Http\\Controllers\\PublicController@index',
        handlerType: 'php_request',
        samplingAttributes: [
            'laravel.route.action' => 'App\\Http\\Controllers\\PublicController@index',
        ],
    );

    expect($rule->getMatchedRate($entryPoint))->toBeNull();
});

it('only applies to web entry points', function (BaseSamplingRule $rule) {
    expect($rule->appliesTo(EntryPointType::Web))->toBeTrue()
        ->and($rule->appliesTo(EntryPointType::Cli))->toBeFalse()
        ->and($rule->appliesTo(EntryPointType::Queue))->toBeFalse();
})->with([
    'route name' => fn () => SamplingRule::forRouteName('x', 1.0),
    'route action' => fn () => SamplingRule::forRouteAction('x', 1.0),
]);

it('throws when constructing a rule with an out-of-range rate', function (Closure $factory) {
    expect(fn () => $factory())->toThrow(InvalidArgumentException::class, 'Sampling rate must be between 0 and 1.');
})->with([
    'route name above 1' => [fn () => SamplingRule::forRouteName('users.*', 1.5)],
    'route action below 0' => [fn () => SamplingRule::forRouteAction('App\\*', -0.1)],
]);

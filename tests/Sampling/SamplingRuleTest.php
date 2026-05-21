<?php

use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Sampling\DeferredSamplerRule;
use Spatie\FlareClient\Sampling\Rules\PathSamplingRule;
use Spatie\FlareClient\Sampling\Rules\UrlSamplingRule;
use Spatie\FlareClient\Sampling\SamplingRule as BaseSamplingRule;
use Spatie\LaravelFlare\Sampling\QueueConnectionSamplingRule;
use Spatie\LaravelFlare\Sampling\QueueNameSamplingRule;
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

it('builds a queue-name rule from the fluent factory', function () {
    $rule = SamplingRule::forQueueName('notifications', 1.0);

    expect($rule)->toBeInstanceOf(QueueNameSamplingRule::class);
});

it('builds a queue-connection rule from the fluent factory', function () {
    $rule = SamplingRule::forQueueConnection('redis', 0.25);

    expect($rule)->toBeInstanceOf(QueueConnectionSamplingRule::class);
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

it('returns null when the array form is missing required keys', function () {
    expect(SamplingRule::fromArray(['pattern' => 'x', 'rate' => 1.0]))->toBeNull();
});

it('returns null when the type does not resolve to a SamplingRule subclass', function () {
    expect(SamplingRule::fromArray(['type' => stdClass::class, 'pattern' => 'x', 'rate' => 1.0]))->toBeNull();
});

it('marks every Laravel rule as deferred', function (BaseSamplingRule $rule) {
    expect($rule)->toBeInstanceOf(DeferredSamplerRule::class);
})->with([
    'route name' => fn () => SamplingRule::forRouteName('admin.*', 1.0),
    'route action' => fn () => SamplingRule::forRouteAction('App\\*', 1.0),
    'queue name' => fn () => SamplingRule::forQueueName('notifications', 1.0),
    'queue connection' => fn () => SamplingRule::forQueueConnection('redis', 1.0),
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

it('accepts a [Controller::class, action] tuple for the route action pattern', function () {
    $rule = SamplingRule::forRouteAction([RouteActionSamplingRule::class, 'index'], 0.5);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');
    $entryPoint->setHandler(
        handlerIdentifier: 'admin/users',
        handlerName: null,
        handlerType: 'php_request',
        samplingAttributes: [
            'laravel.route.action' => RouteActionSamplingRule::class.'@index',
        ],
    );

    expect($rule->getMatchedRate($entryPoint))->toBe(0.5);
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

it('only applies route rules to web entry points', function (BaseSamplingRule $rule) {
    expect($rule->appliesTo(EntryPointType::Web))->toBeTrue()
        ->and($rule->appliesTo(EntryPointType::Cli))->toBeFalse()
        ->and($rule->appliesTo(EntryPointType::Queue))->toBeFalse();
})->with([
    'route name' => fn () => SamplingRule::forRouteName('x', 1.0),
    'route action' => fn () => SamplingRule::forRouteAction('x', 1.0),
]);

it('only applies queue rules to queue entry points', function (BaseSamplingRule $rule) {
    expect($rule->appliesTo(EntryPointType::Queue))->toBeTrue()
        ->and($rule->appliesTo(EntryPointType::Web))->toBeFalse()
        ->and($rule->appliesTo(EntryPointType::Cli))->toBeFalse();
})->with([
    'queue name' => fn () => SamplingRule::forQueueName('x', 1.0),
    'queue connection' => fn () => SamplingRule::forQueueConnection('x', 1.0),
]);

it('matches the queue name once the handler is resolved', function () {
    $rule = SamplingRule::forQueueName('notifications', 0.5);

    $entryPoint = new EntryPoint(EntryPointType::Queue, 'App\\Jobs\\SendInvoice');
    $entryPoint->setHandler(
        handlerIdentifier: 'App\\Jobs\\SendInvoice',
        handlerName: null,
        handlerType: 'laravel_job',
        samplingAttributes: [
            'laravel.job.queue.name' => 'notifications',
            'laravel.job.queue.connection_name' => 'redis',
        ],
    );

    expect($rule->getMatchedRate($entryPoint))->toBe(0.5);
});

it('returns null when the queue name attribute is missing', function () {
    $rule = SamplingRule::forQueueName('notifications', 1.0);

    $entryPoint = new EntryPoint(EntryPointType::Queue, 'App\\Jobs\\SendInvoice');
    $entryPoint->setHandler('App\\Jobs\\SendInvoice', null, 'laravel_job');

    expect($rule->getMatchedRate($entryPoint))->toBeNull();
});

it('matches the queue connection against the pattern', function () {
    $rule = SamplingRule::forQueueConnection('redis*', 0.25);

    $entryPoint = new EntryPoint(EntryPointType::Queue, 'App\\Jobs\\SendInvoice');
    $entryPoint->setHandler(
        handlerIdentifier: 'App\\Jobs\\SendInvoice',
        handlerName: null,
        handlerType: 'laravel_job',
        samplingAttributes: [
            'laravel.job.queue.connection_name' => 'redis-cluster',
        ],
    );

    expect($rule->getMatchedRate($entryPoint))->toBe(0.25);
});

it('does not match unrelated queue connections', function () {
    $rule = SamplingRule::forQueueConnection('redis', 1.0);

    $entryPoint = new EntryPoint(EntryPointType::Queue, 'App\\Jobs\\SendInvoice');
    $entryPoint->setHandler(
        handlerIdentifier: 'App\\Jobs\\SendInvoice',
        handlerName: null,
        handlerType: 'laravel_job',
        samplingAttributes: [
            'laravel.job.queue.connection_name' => 'database',
        ],
    );

    expect($rule->getMatchedRate($entryPoint))->toBeNull();
});

it('throws when constructing a rule with an out-of-range rate', function (Closure $factory) {
    expect(fn () => $factory())->toThrow(InvalidArgumentException::class, 'Sampling rate must be between 0 and 1.');
})->with([
    'route name above 1' => [fn () => SamplingRule::forRouteName('users.*', 1.5)],
    'route action below 0' => [fn () => SamplingRule::forRouteAction('App\\*', -0.1)],
    'queue name above 1' => [fn () => SamplingRule::forQueueName('default', 1.5)],
    'queue connection below 0' => [fn () => SamplingRule::forQueueConnection('redis', -0.1)],
]);

<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\FlareClient\Support\Redactor;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(MakesHttpRequests::class);

it('returns route name in context data', function () {
    $route = Route::get('/route/', fn () => null)->name('routeName');

    $request = createRequest('GET', '/route');

    $route->bind($request);

    $request->setRouteResolver(fn () => $route);

    $provider = new LaravelRequestAttributesProvider(new Redactor());

    $attributes = $provider->toArray($request);

    expect($attributes['laravel.route.name'])->toBe('routeName');
});

it('returns route parameters in context data', function () {
    $route = Route::get('/route/{parameter}/{otherParameter}', fn () => null);

    $request = createRequest('GET', '/route/value/second');

    $route->bind($request);

    $request->setRouteResolver(function () use ($route) {
        return $route;
    });

    $provider = new LaravelRequestAttributesProvider(new Redactor());

    $attributes = $provider->toArray($request);

    $this->assertSame([
        'parameter' => 'value',
        'otherParameter' => 'second',
    ], $attributes['laravel.route.parameters']);
});

it('will call the to flare method on route parameters when it exists', function () {
    $route = Route::get('/route/{user}', function ($user) {
    });

    $request = createRequest('GET', '/route/1');

    $route->bind($request);

    $request->setRouteResolver(function () use ($route) {
        $route->setParameter('user', new class {
            public function toFlare(): array
            {
                return ['stripped'];
            }
        });

        return $route;
    });

    $provider = new LaravelRequestAttributesProvider(new Redactor());

    $attributes = $provider->toArray($request);

    $this->assertSame([
        'user' => ['stripped'],
    ], $attributes['laravel.route.parameters']);
});

it('returns the url', function () {
    $request = createRequest('GET', '/route', []);

    $provider = new LaravelRequestAttributesProvider(new Redactor());

    $attributes = $provider->toArray($request);

    expect($attributes['url.full'])->toBe('http://localhost/route');
});

it('returns the cookies', function () {
    $request = createRequest('GET', '/route', [], ['cookie' => 'noms']);

    $provider = new LaravelRequestAttributesProvider(new Redactor());

    $attributes = $provider->toArray($request);

    expect($attributes['http.request.cookies'])->toBe(['cookie' => 'noms']);
});

it('returns the authenticated user', function () {
    $user = new User();
    $user->forceFill([
        'id' => 1,
        'email' => 'john@example.com',
    ]);

    $request = createRequest('GET', '/route', [], ['cookie' => 'noms']);
    $request->setUserResolver(fn () => $user);

    $provider = new LaravelRequestAttributesProvider(new Redactor());
    $attributes = $provider->toArray($request);

    expect($attributes['laravel.user'])->toBe($user->toArray());
});

it('the authenticated user model has a to flare method it will be used to collect user data', function () {
    $user = new class extends User {
        public function toFlare()
        {
            return ['id' => $this->id];
        }
    };

    $user->forceFill([
        'id' => 1,
        'email' => 'john@example.com',
    ]);

    $request = createRequest('GET', '/route', [], ['cookie' => 'noms']);
    $request->setUserResolver(fn () => $user);

    $provider = new LaravelRequestAttributesProvider(new Redactor());
    $attributes = $provider->toArray($request);

    expect($attributes['laravel.user'])->toBe(['id' => $user->id]);
});

it('the authenticated user model has no matching method it will return no user data', function () {
    $user = new class {
        public function toArray(): array
        {
            return ['name' => 'John Doe'];
        }
    };

    $request = createRequest('GET', '/route', [], ['cookie' => 'noms']);
    $request->setUserResolver(fn () => $user);

    $provider = new LaravelRequestAttributesProvider(new Redactor());
    $attributes = $provider->toArray($request);

    expect($attributes['laravel.user'])->toBe(['name' => 'John Doe']);
});

it('the authenticated user model is broken it will return no user data', function () {
    $user = new class extends User {
        protected $appends = ['invalid'];
    };

    $request = createRequest('GET', '/route', [], ['cookie' => 'noms']);
    $request->setUserResolver(fn () => $user);

    $provider = new LaravelRequestAttributesProvider(new Redactor());
    $attributes = $provider->toArray($request);

    expect($attributes)->not()->toHaveKey('laravel.user');
});

function createRequest($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): Request
{
    $helper = new class {
        use MakesHttpRequests {
            extractFilesFromDataArray as public;
            prepareUrlForRequest as public;
        }
    };

    $files = array_merge(
        $files,
        $helper->extractFilesFromDataArray($parameters)
    );

    $symfonyRequest = SymfonyRequest::create(
        $helper->prepareUrlForRequest($uri),
        $method,
        $parameters,
        $cookies,
        $files,
        array_replace([], $server),
        $content
    );

    return Request::createFromBase($symfonyRequest);
}

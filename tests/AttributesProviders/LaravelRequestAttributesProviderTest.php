<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\FlareClient\Support\Redactor;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Spatie\LaravelFlare\AttributesProviders\LaravelRouteAttributesProvider;
use Spatie\LaravelFlare\AttributesProviders\LaravelUserAttributesProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(MakesHttpRequests::class);

function buildLaravelRequestAttributes(Request $request): array
{
    $attributes = (new LaravelRequestAttributesProvider(
        new Redactor(),
        $request,
    ))->toArray();

    if ($route = $request->route()) {
        $attributes = [
            ...$attributes,
            ...(new LaravelRouteAttributesProvider($route, $request->getMethod()))->toArray(),
        ];
    }

    if (is_object($user = rescue(fn () => $request->user(), null, false))) {
        $attributes = [
            ...$attributes,
            ...(new LaravelUserAttributesProvider($user))->toArray(),
        ];
    }

    return $attributes;
}

it('returns route name in context data', function () {
    $route = Route::get('/route/', fn () => null)->name('routeName');

    $request = createRequest('GET', '/route');

    $route->bind($request);

    $request->setRouteResolver(fn () => $route);

    $attributes = buildLaravelRequestAttributes($request);

    expect($attributes['laravel.route.name'])->toBe('routeName');
});

it('returns route parameters in context data', function () {
    $route = Route::get('/route/{parameter}/{otherParameter}', fn () => null);

    $request = createRequest('GET', '/route/value/second');

    $route->bind($request);

    $request->setRouteResolver(function () use ($route) {
        return $route;
    });

    $attributes = buildLaravelRequestAttributes($request);

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
        $route->setParameter('user', new class() {
            public function toFlare(): array
            {
                return ['stripped'];
            }
        });

        return $route;
    });

    $attributes = buildLaravelRequestAttributes($request);

    $this->assertSame([
        'user' => ['stripped'],
    ], $attributes['laravel.route.parameters']);
});

it('returns the url', function () {
    $request = createRequest('GET', '/route', []);

    $attributes = buildLaravelRequestAttributes($request);

    expect($attributes['url.full'])->toBe('http://localhost/route');
});

it('returns the cookies', function () {
    $request = createRequest('GET', '/route', [], ['cookie' => 'noms']);

    $attributes = buildLaravelRequestAttributes($request);

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

    $attributes = buildLaravelRequestAttributes($request);

    expect($attributes['user.email'])->toBe('john@example.com');
    expect($attributes['user.id'])->toBe(1);
    expect($attributes)->not()->toHaveKeys([
        'user.full_name',
        'user.context',
    ]);
});

it('the authenticated user model has a to flare method it will be used to collect user data', function () {
    $user = new class() extends User {
        public function toFlare()
        {
            return ['role' => 'admin'];
        }
    };

    $user->forceFill([
        'id' => 1,
        'email' => 'john@example.com',
    ]);

    $request = createRequest('GET', '/route', [], ['cookie' => 'noms']);
    $request->setUserResolver(fn () => $user);

    $attributes = buildLaravelRequestAttributes($request);

    expect($attributes['user.email'])->toBe('john@example.com');
    expect($attributes['user.id'])->toBe(1);
    expect($attributes['user.attributes'])->toBe(['role' => 'admin']);
    expect($attributes)->not()->toHaveKeys([
        'user.full_name',
    ]);
});

it('the authenticated user cannot be deduced so no attributes are added', function (
    $user
) {
    $request = createRequest('GET', '/route', [], ['cookie' => 'noms']);
    $request->setUserResolver(fn () => $user);

    $attributes = buildLaravelRequestAttributes($request);

    expect($attributes)->not()->toHaveKeys([
        'user.full_name',
        'user.email',
        'user.id',
        'user.attributes',
    ]);
})->with([
    'no user resolver' => fn () => null,
    'empty class' => fn () => new class() {
    },
    'empty user' => fn () => new User(),
    'array' => fn () => [],
]);

function createRequest($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): Request
{
    $helper = new class() {
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

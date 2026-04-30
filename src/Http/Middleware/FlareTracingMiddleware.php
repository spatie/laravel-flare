<?php

namespace Spatie\LaravelFlare\Http\Middleware;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Spatie\LaravelFlare\AttributesProviders\LaravelRouteAttributesProvider;
use Spatie\LaravelFlare\AttributesProviders\LaravelUserAttributesProvider;
use Spatie\LaravelFlare\Enums\LaravelCollectType;
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\LaravelFlare\FlareConfig;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class FlareTracingMiddleware
{
    public function __construct(
        protected Tracer $tracer,
        protected Lifecycle $lifecycle,
        protected Application $app,
        protected Redactor $redactor,
        protected FlareConfig $config,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $ignorePaths = [
            '_debugbar',
            'telescope',
            'horizon',
        ];

        if (
            Str::startsWith($request->decodedPath(), $ignorePaths)
            || preg_match('/^livewire(-[a-f0-9]+)?\/livewire(\.min)?\.js/', $request->decodedPath())
        ) {
            $this->tracer->unsample();

            return $next($request);
        }

        Flare::request()?->recordStartFromSymfonyRequest(
            $request,
            user: $this->resolveUserProvider($request),
        );
        Flare::routing()?->recordGlobalBeforeMiddlewareStart();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $attributes = (new LaravelRequestAttributesProvider(
            $this->redactor,
            $request,
            includeContents: false,
            includeLivewireComponents: $this->livewireOption('include_livewire_components', false),
            ignoreLivewireComponents: $this->livewireOption('ignore', []),
        ))->toArray();

        if ($route = $this->resolveRouteProvider($request)) {
            $attributes = [...$attributes, ...$route->toArray()];
        }

        if ($user = $this->resolveUserProvider($request)) {
            $attributes = [...$attributes, ...$user->toArray()];
        }

        Flare::request()?->recordEndFromSymfonyResponse($response, $attributes);

        $this->lifecycle->terminating();
    }

    protected function resolveUserProvider(Request $request): ?LaravelUserAttributesProvider
    {
        try {
            $user = $request->user();
        } catch (Throwable) {
            return null;
        }

        if (! is_object($user)) {
            return null;
        }

        return new LaravelUserAttributesProvider($user);
    }

    protected function resolveRouteProvider(Request $request): ?LaravelRouteAttributesProvider
    {
        /** @var ?Route $route */
        $route = $request->route();

        if (! $route instanceof Route) {
            return null;
        }

        return new LaravelRouteAttributesProvider($route, $request->getMethod());
    }

    protected function livewireOption(string $key, mixed $default): mixed
    {
        $options = $this->config->collects[LaravelCollectType::LivewireComponents->value]['options'] ?? [];

        return $options[$key] ?? $default;
    }
}

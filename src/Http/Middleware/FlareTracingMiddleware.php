<?php

namespace Spatie\LaravelFlare\Http\Middleware;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Spatie\FlareClient\AttributesProviders\SymfonyResponseAttributesProvider;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Support\Redactor;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Spatie\LaravelFlare\AttributesProviders\LaravelRouteAttributesProvider;
use Spatie\LaravelFlare\AttributesProviders\LaravelUserAttributesProvider;
use Spatie\LaravelFlare\Enums\LaravelCollectType;
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\Support\LivewireComponentFinder;
use Symfony\Component\HttpFoundation\Response;

class FlareTracingMiddleware
{
    public static ?LaravelRouteAttributesProvider $routeAttributesProvider = null;

    private LaravelRequestAttributesProvider $requestAttributesProvider;

    public function __construct(
        protected Lifecycle $lifecycle,
        protected Application $app,
        protected Redactor $redactor,
        protected LivewireComponentFinder $livewireComponentFinder,
        protected FlareConfig $config,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        self::$routeAttributesProvider = null;

        $livewireOptions = $this->config->collects[LaravelCollectType::LivewireComponents->value]['options'] ?? [];

        $this->requestAttributesProvider = new LaravelRequestAttributesProvider(
            $this->redactor,
            $this->livewireComponentFinder,
            $request,
            includeContents: true,
            includeLivewireComponents: $livewireOptions['include_livewire_components'] ?? false,
            ignoreLivewireComponents: $livewireOptions['ignore_livewire_components'] ?? [],
        );

        Flare::request()?->recordStart($this->requestAttributesProvider);
        Flare::routing()?->recordGlobalBeforeMiddlewareStart();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (isset($this->requestAttributesProvider)) {
            Flare::request()?->recordEnd(
                requestAttributesProvider: $this->requestAttributesProvider,
                responseAttributesProvider: new SymfonyResponseAttributesProvider($this->redactor, $response),
                routeAttributesProvider: self::$routeAttributesProvider ?? LaravelRouteAttributesProvider::fromRequest($request),
                userAttributesProvider: LaravelUserAttributesProvider::fromRequest($request),
            );
        }

        self::$routeAttributesProvider = null;

        $this->lifecycle->terminating();
    }
}

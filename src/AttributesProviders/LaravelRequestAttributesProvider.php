<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Routing\Route;
use Livewire\LivewireManager;
use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider as BaseRequestAttributesProvider;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class LaravelRequestAttributesProvider extends BaseRequestAttributesProvider
{
    public function toArray(Request $request, bool $includeContents = true, bool $includeLivewireComponents = false): array
    {
        if (! $request instanceof LaravelRequest) {
            return parent::toArray($request, $includeContents);
        }

        $attributes = [
            ...parent::toArray($request, $includeContents),
            ...$this->getRoute($request),
        ];

        if (! $this->isRunningLiveWire($request) || ! $includeLivewireComponents) {
            return $attributes;
        }

        try {
            $provider = new LivewireAttributesProvider();

            $livewireManager = app(LivewireManager::class);

            return array_merge(
                $attributes,
                $provider->toArray($request, $livewireManager)
            );
        } catch (Throwable) {
            return $attributes;
        }
    }

    protected function getUser(Request $request): ?object
    {
        if (! $request instanceof LaravelRequest) {
            return null;
        }

        try {
            $user = $request->user();

            if (! is_object($user)) {
                return null;
            }

            return $user;
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function getRoute(LaravelRequest $request): array
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return [];
        }

        return [
            'http.route' => $route->uri(),
            'laravel.route.name' => $route->getName(),
            'laravel.route.parameters' => $this->getRouteParameters($route),
            'laravel.route.action' => $route->getActionName(),
            'laravel.route.middleware' => array_values($route->gatherMiddleware()),

            'flare.entry_point.class' => $route->getActionName(),
        ];
    }

    /** @return array<int, mixed> */
    protected function getRouteParameters(
        Route $route
    ): array {
        try {
            return collect($route->parameters)
                ->map(fn ($parameter) => $parameter instanceof Model ? $parameter->withoutRelations() : $parameter)
                ->map(function ($parameter) {
                    return is_object($parameter) && method_exists($parameter, 'toFlare') ? $parameter->toFlare() : $parameter;
                })
                ->toArray();
        } catch (Throwable) {
            return [];
        }
    }

    protected function isRunningLiveWire(LaravelRequest $request): bool
    {
        return $request->hasHeader('x-livewire') && $request->hasHeader('referer');
    }
}

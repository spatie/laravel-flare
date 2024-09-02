<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Routing\Route;
use Livewire\LivewireManager;
use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider as BaseRequestAttributesProvider;
use Spatie\FlareClient\Support\Redactor;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class LaravelRequestAttributesProvider extends BaseRequestAttributesProvider
{
    public function toArray(Request $request, bool $includeLivewireComponents = false): array
    {
        if (! $request instanceof LaravelRequest) {
            return parent::toArray($request);
        }

        $attributes = [
            ...parent::toArray($request),
            ...$this->getUser($request),
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

    protected function getUser(LaravelRequest $request): array
    {
        try {
            $user = $request->user();

            if (! $user) {
                return [];
            }

            if (method_exists($user, 'toFlare')) {
                return ['laravel.user' => $user->toFlare()];
            }

            if (method_exists($user, 'toArray')) {
                return ['laravel.user' => $user->toArray()];
            }
        } catch (Throwable $e) {
            return [];
        }

        return [];
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
            'laravel.route.controller' => $route->getActionName(),
            'laravel.route.middleware' => array_values($route->gatherMiddleware()),
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
                    return method_exists($parameter, 'toFlare') ? $parameter->toFlare() : $parameter;
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

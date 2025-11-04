<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route;
use Illuminate\Routing\ViewController;
use Livewire\LivewireManager;
use ReflectionFunction;
use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider as BaseRequestAttributesProvider;
use Spatie\LaravelFlare\Enums\LaravelRouteActionType;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class LaravelRequestAttributesProvider extends BaseRequestAttributesProvider
{
    /**
     * @param array<string> $ignoreLivewireComponents
     */
    public function toArray(
        Request $request,
        bool $includeContents = true,
        bool $includeLivewireComponents = false,
        array $ignoreLivewireComponents = []
    ): array {
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
                $provider->toArray($request, $livewireManager, $ignoreLivewireComponents),
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
            'laravel.route.middleware' => array_values($route->gatherMiddleware()),
            ...$this->getActionAttributes($route),
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

    protected function getActionAttributes(Route $route): array
    {
        $actionName = $route->getActionName();
        $type = LaravelRouteActionType::Controller;

        if ($actionName === '\\'.ViewController::class && $route->hasParameter('view')) {
            $actionName = "view: {$route->parameter('view')}";
            $type = LaravelRouteActionType::View;
        }

        if($actionName === '\\'.RedirectController::class && $route->hasParameter('destination')) {
            $actionName = "redirect: {$route->parameter('destination')}";
            $type = LaravelRouteActionType::Redirect;
        }

        if ($actionName === 'Closure' && $route->getAction('uses') instanceof Closure) {
            try {
                $closure = $route->getAction('uses');

                $reflection = new ReflectionFunction($closure);

                $filename = str_replace(
                    rtrim(base_path(), '/').'/',
                    '',
                    $reflection->getFileName(),
                );

                $actionName = "closure: {$filename}";
                $type = LaravelRouteActionType::Closure;
            } catch (Throwable) {
            }
        }

        return [
            'laravel.route.action' => $actionName,
            'laravel.route.action_type' => $type,
            'flare.entry_point.class' => $actionName,
        ];
    }
}

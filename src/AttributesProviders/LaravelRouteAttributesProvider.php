<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route;
use Illuminate\Routing\ViewController;
use ReflectionFunction;
use Spatie\FlareClient\Contracts\EntryPointHandlerProvider;
use Spatie\FlareClient\Contracts\RouteAttributesProvider;
use Spatie\LaravelFlare\Enums\LaravelRouteActionType;
use Throwable;

class LaravelRouteAttributesProvider implements RouteAttributesProvider, EntryPointHandlerProvider
{
    public function __construct(
        protected ?Route $route = null,
        protected ?string $method = null,
    ) {
    }

    public function toArray(): array
    {
        if (! $this->route instanceof Route) {
            return [];
        }

        ['name' => $actionName, 'type' => $actionType] = $this->resolveAction($this->route);

        return [
            'http.route' => $this->route->uri(),
            'laravel.route.name' => $this->route->getName(),
            'laravel.route.parameters' => $this->getRouteParameters($this->route),
            'laravel.route.middleware' => array_values($this->route->gatherMiddleware()),
            'laravel.route.action' => $actionName,
            'laravel.route.action_type' => $actionType,
        ];
    }

    public function route(): ?string
    {
        return $this->route?->uri();
    }

    public function method(): ?string
    {
        return $this->method;
    }

    public function entryPointHandlerName(): ?string
    {
        if (! $this->route instanceof Route) {
            return null;
        }

        return $this->resolveAction($this->route)['name'];
    }

    public function entryPointHandlerType(): ?string
    {
        if (! $this->route instanceof Route) {
            return null;
        }

        return $this->resolveAction($this->route)['type']->entryPointHandlerType();
    }

    public function entryPointHandlerIdentifier(): ?string
    {
        if (! $this->route instanceof Route) {
            return null;
        }

        return $this->route->uri();
    }

    /** @return array{name: ?string, type: LaravelRouteActionType} */
    protected function resolveAction(Route $route): array
    {
        $actionName = $route->getActionName();
        $type = LaravelRouteActionType::Controller;

        if ($actionName === '\\'.ViewController::class
            && ($view = $route->parameter('view'))
            && is_string($view)
        ) {
            return ['name' => "view: {$view}", 'type' => LaravelRouteActionType::View];
        }

        if ($actionName === '\\'.RedirectController::class
            && ($destination = $route->parameter('destination'))
            && is_string($destination)
        ) {
            return ['name' => "redirect: {$destination}", 'type' => LaravelRouteActionType::Redirect];
        }

        if ($actionName === 'Closure' && $route->getAction('uses') instanceof Closure) {
            try {
                $reflection = new ReflectionFunction($route->getAction('uses'));

                $filename = str_replace(
                    rtrim(base_path(), '/').'/',
                    '',
                    $reflection->getFileName() ?: 'unknown file',
                );

                return ['name' => "closure: {$filename}", 'type' => LaravelRouteActionType::Closure];
            } catch (Throwable) {
                return ['name' => null, 'type' => LaravelRouteActionType::Closure];
            }
        }

        return ['name' => $actionName, 'type' => $type];
    }

    /** @return array<int, mixed> */
    protected function getRouteParameters(Route $route): array
    {
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
}

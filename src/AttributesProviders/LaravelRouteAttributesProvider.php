<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route;
use Illuminate\Routing\ViewController;
use ReflectionFunction;
use Spatie\FlareClient\Contracts\EntryPointHandlerProvider;
use Spatie\FlareClient\Contracts\RouteAttributesProvider;
use Spatie\FlareClient\Contracts\SamplingAttributesProvider;
use Spatie\LaravelFlare\Enums\LaravelRouteActionType;
use Throwable;

class LaravelRouteAttributesProvider implements RouteAttributesProvider, EntryPointHandlerProvider, SamplingAttributesProvider
{
    /** @var array{name: ?string, type: LaravelRouteActionType} */
    protected array $resolvedAction;

    public static function fromRequest(Request $request): ?self
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return null;
        }

        return new self($route, $request->getMethod());
    }

    public function __construct(
        protected Route $route,
        protected string $method,
    ) {
        $this->resolvedAction = $this->resolveAction($this->route);
    }

    public function toArray(): array
    {
        return [
            'http.route' => $this->route->uri(),
            'laravel.route.name' => $this->route->getName(),
            'laravel.route.parameters' => $this->getRouteParameters($this->route),
            'laravel.route.middleware' => array_values($this->route->gatherMiddleware()),
            'laravel.route.action' => $this->resolvedAction['name'],
            'laravel.route.action_type' => $this->resolvedAction['type'],
        ];
    }

    public function route(): string
    {
        return $this->route->uri();
    }

    public function method(): string
    {
        return $this->method;
    }

    public function entryPointHandlerName(): ?string
    {
        return $this->resolvedAction['name'];
    }

    public function entryPointHandlerType(): ?string
    {
        return $this->resolvedAction['type']->entryPointHandlerType();
    }

    public function entryPointHandlerIdentifier(): ?string
    {
        return $this->route->uri();
    }

    public function samplingAttributes(): array
    {
        return [
            'laravel.route.name' => $this->route->getName(),
            'laravel.route.action' => $this->resolvedAction['name'],
        ];
    }

    /** @return array{name: ?string, type: LaravelRouteActionType} */
    protected function resolveAction(Route $route): array
    {
        $actionName = $route->getActionName();

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

        return ['name' => $actionName, 'type' => LaravelRouteActionType::Controller];
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

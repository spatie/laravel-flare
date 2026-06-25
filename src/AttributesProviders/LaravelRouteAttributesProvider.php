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
        $attributes = [
            'http.route' => $this->route->uri(),
            'laravel.route.name' => $this->route->getName(),
            'laravel.route.parameters' => $this->normalizeValues($this->route->parameters ?? []),
            'laravel.route.middleware' => array_values($this->route->gatherMiddleware()),
            'laravel.route.action' => $this->resolvedAction['name'],
            'laravel.route.action_type' => $this->resolvedAction['type'],
        ];

        if (method_exists($this->route, 'getMetadata')) {
            $attributes['laravel.route.metadata'] = $this->normalizeValues((array) $this->route->getMetadata());
        }

        return $attributes;
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

    /**
     * @param array<int|string, mixed> $values
     *
     * @return array<int|string, mixed>
     */
    protected function normalizeValues(array $values): array
    {
        try {
            return array_map(function ($value) {
                if ($value instanceof Model) {
                    $value = $value->withoutRelations();
                }

                if (is_object($value) && method_exists($value, 'toFlare')) {
                    return $value->toFlare();
                }

                return $value;
            }, $values);
        } catch (Throwable) {
            return [];
        }
    }
}

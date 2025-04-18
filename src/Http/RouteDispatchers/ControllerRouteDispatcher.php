<?php

namespace Spatie\LaravelFlare\Http\RouteDispatchers;

use Illuminate\Routing\Contracts\ControllerDispatcher;
use Illuminate\Routing\Route;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Http\RouteDispatchers\Concerns\DispatchesRoutes;

class ControllerRouteDispatcher implements ControllerDispatcher
{
    use DispatchesRoutes;

    public function __construct(
        protected Tracer $tracer,
        protected ControllerDispatcher $dispatcher,
    ) {
    }

    public function dispatch(Route $route, $controller, $method)
    {
        return $this->wrapDispatcher(fn () => $this->dispatcher->dispatch($route, $controller, $method));
    }

    public function getMiddleware($controller, $method): array
    {
        return $this->dispatcher->getMiddleware($controller, $method);
    }
}

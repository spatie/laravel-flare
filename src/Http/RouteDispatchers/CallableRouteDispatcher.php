<?php

namespace Spatie\LaravelFlare\Http\RouteDispatchers;

use Illuminate\Routing\Contracts\CallableDispatcher;
use Illuminate\Routing\Route;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Http\RouteDispatchers\Concerns\DispatchesRoutes;

class CallableRouteDispatcher implements CallableDispatcher
{
    use DispatchesRoutes;

    public function __construct(
        protected Tracer $tracer,
        protected CallableDispatcher $dispatcher,
    ) {
    }

    public function dispatch(Route $route, $callable): mixed
    {
        return $this->wrapDispatcher(fn () => $this->dispatcher->dispatch($route, $callable));
    }
}

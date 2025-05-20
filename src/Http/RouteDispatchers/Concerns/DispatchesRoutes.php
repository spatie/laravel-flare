<?php

namespace Spatie\LaravelFlare\Http\RouteDispatchers\Concerns;

use Closure;
use Spatie\LaravelFlare\Facades\Flare;

trait DispatchesRoutes
{
    protected function wrapDispatcher(Closure $dispatch): mixed
    {
        Flare::routing()?->recordBeforeMiddlewareEnd();

        $dispatched = $dispatch();

        Flare::response()?->recordStart();

        return $dispatched;
    }
}

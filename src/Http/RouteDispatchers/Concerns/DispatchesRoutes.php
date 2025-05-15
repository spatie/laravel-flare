<?php

namespace Spatie\LaravelFlare\Http\RouteDispatchers\Concerns;

use Closure;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\LaravelFlare\Facades\Flare;

trait DispatchesRoutes
{
    protected function wrapDispatcher(Closure $dispatch): mixed
    {
        Flare::routing()->recordBeforeMiddlewareEnd();

        $dispatched = $dispatch();

        $this->tracer->startSpan(
            'Response',
            attributes: ['flare.span_type' => SpanType::Response]
        );

        return $dispatched;
    }
}

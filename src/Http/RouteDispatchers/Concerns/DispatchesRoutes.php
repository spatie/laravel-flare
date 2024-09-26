<?php

namespace Spatie\LaravelFlare\Http\RouteDispatchers\Concerns;

use Closure;
use Spatie\LaravelFlare\Enums\SpanType;

trait DispatchesRoutes
{
    protected function wrapDispatcher(Closure $dispatch): mixed
    {
        if ($this->tracer->hasCurrentSpan(SpanType::LocalMiddlewareBefore)) {
            $this->tracer->endSpan($this->tracer->currentSpan());
        }

        $dispatched = $dispatch();

        $this->tracer->startSpan(
            'Response',
            attributes: ['flare.span_type' => SpanType::Response]
        );

        return $dispatched;
    }
}

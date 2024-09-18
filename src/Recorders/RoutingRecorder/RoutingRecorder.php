<?php

namespace Spatie\LaravelFlare\Recorders\RoutingRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Events\Routing;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Enums\SpanType;

class RoutingRecorder implements SpansRecorder
{
    public static function type(): string|RecorderType
    {
        return RecorderType::Routing;
    }

    public function __construct(
        protected Tracer $tracer,
        protected Application $app,
        protected Dispatcher $dispatcher,
    ) {
    }

    public function start(): void
    {
        $this->dispatcher->listen(Routing::class, function (Routing $event) {
            if (! $this->tracer->isSampling()) {
                return;
            }

            if ($this->tracer->hasCurrentSpan(SpanType::GlobalMiddlewareBefore)) {
                $this->tracer->endCurrentSpan();
            }

            $this->tracer->startSpan(
                'Routing',
                attributes: ['flare.span_type' => SpanType::Routing]
            );
        });

        $this->dispatcher->listen(RouteMatched::class, function () {
            if (! $this->tracer->isSampling()) {
                return;
            }

            if ($this->tracer->hasCurrentSpan(SpanType::Routing)) {
                $this->tracer->endCurrentSpan();
            }

            $this->tracer->startSpan(
                'Middleware (local, before)',
                attributes: ['flare.span_type' => SpanType::LocalMiddlewareBefore]
            );
        });

        $this->dispatcher->listen(RequestHandled::class, function () {
            if (! $this->tracer->isSampling()) {
                return;
            }

            if ($this->tracer->hasCurrentSpan(SpanType::Response)) {
                $this->tracer->endCurrentSpan();
            }

            $this->tracer->startSpan(
                'Terminating',
                attributes: ['flare.span_type' => SpanType::Terminating]
            );
        });
    }

    public function reset(): void
    {

    }

    public function getSpans(): array
    {
        return [];
    }
}

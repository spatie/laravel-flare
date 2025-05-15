<?php

namespace Spatie\LaravelFlare\Recorders\RoutingRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Events\Routing;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Enums\SpanType as LaravelSpanType;
use Spatie\LaravelFlare\Facades\Flare;

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

    public function boot(): void
    {
        $this->dispatcher->listen(Routing::class, function (Routing $event) {
            if (! $this->tracer->isSampling()) {
                return;
            }

            if ($this->tracer->hasCurrentSpan(LaravelSpanType::GlobalMiddlewareBefore)) {
                $this->tracer->endSpan();
            }

            $this->tracer->startSpan(
                'Routing',
                attributes: ['flare.span_type' => LaravelSpanType::Routing]
            );
        });

        $this->dispatcher->listen(RouteMatched::class, function () {
            if (! $this->tracer->isSampling()) {
                return;
            }

            if ($this->tracer->hasCurrentSpan(LaravelSpanType::Routing)) {
                $this->tracer->endSpan();
            }

            $this->tracer->startSpan(
                'Middleware (local, before)',
                attributes: ['flare.span_type' => LaravelSpanType::LocalMiddlewareBefore]
            );
        });

        $this->dispatcher->listen(RequestHandled::class, function () {
            if (! $this->tracer->isSampling()) {
                return;
            }

            if ($this->tracer->hasCurrentSpan(LaravelSpanType::Response)) {
                $this->tracer->endSpan();
            }

            if($this->tracer->hasCurrentSpan(SpanType::Request)) {
                $this->tracer->endSpan();
            }

            Flare::application()->recordTerminating();
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

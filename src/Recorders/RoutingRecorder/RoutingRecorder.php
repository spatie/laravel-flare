<?php

namespace Spatie\LaravelFlare\Recorders\RoutingRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Events\Routing;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\RoutingRecorder\RoutingRecorder as BaseRoutingRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Facades\Flare;

class RoutingRecorder extends BaseRoutingRecorder
{
    public static function type(): string|RecorderType
    {
        return RecorderType::Routing;
    }

    public function __construct(
        protected Tracer $tracer,
        protected Application $app,
        protected Dispatcher $dispatcher,
        protected BackTracer $backTracer,
        protected array $config,
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public function boot(): void
    {
        $this->dispatcher->listen(Routing::class, function () {
            $this->recordGlobalBeforeMiddlewareEnd();
            $this->recordRoutingStart();
        });

        $this->dispatcher->listen(RouteMatched::class, function () {
            $this->recordRoutingEnd();
            $this->recordBeforeMiddlewareStart();
        });

        $this->dispatcher->listen(RequestHandled::class, function () {
            if (! $this->tracer->isSampling()) {
                return;
            }

            Flare::response()?->recordEnd();

            if ($this->tracer->hasCurrentSpan(SpanType::Request)) {
                $this->tracer->endSpan();
            }

            Flare::application()->recordTerminating();
        });
    }
}

<?php

namespace Spatie\LaravelFlare\Recorders\RoutingRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Events\Routing;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Recorders\RoutingRecorder\RoutingRecorder as BaseRoutingRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelRouteAttributesProvider;
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\LaravelFlare\Http\Middleware\FlareTracingMiddleware;

class RoutingRecorder extends BaseRoutingRecorder
{
    public static function type(): string|RecorderType
    {
        return RecorderType::Routing;
    }

    public function __construct(
        Tracer $tracer,
        EntryPointResolver $entryPointResolver,
        protected Lifecycle $lifecycle,
        protected Application $app,
        protected Dispatcher $dispatcher,
        BackTracer $backTracer,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $entryPointResolver, $config);
    }

    public function boot(): void
    {
        $this->dispatcher->listen(Routing::class, function () {
            $this->recordGlobalBeforeMiddlewareEnd();
            $this->recordRoutingStart();
        });

        $this->dispatcher->listen(RouteMatched::class, function (RouteMatched $event) {
            FlareTracingMiddleware::$routeAttributesProvider = LaravelRouteAttributesProvider::fromRequest($event->request);

            $this->recordRoutingEnd(FlareTracingMiddleware::$routeAttributesProvider);

            $this->recordBeforeMiddlewareStart();
        });

        $this->dispatcher->listen(RequestHandled::class, function (RequestHandled $event) {
            if (! $this->tracer->isSampling()) {
                return;
            }

            FlareTracingMiddleware::$routeAttributesProvider ??= LaravelRouteAttributesProvider::fromRequest($event->request);

            // In some cases when an error happens in one of these stages (or an abort is thrown) the only event to catch this is the RequestHandled event.
            Flare::routing()?->recordGlobalBeforeMiddlewareEnd();
            Flare::routing()?->recordBeforeMiddlewareEnd();
            Flare::routing()?->recordRoutingEnd(FlareTracingMiddleware::$routeAttributesProvider);

            Flare::response()?->recordEnd();
        });
    }
}

<?php

namespace Spatie\LaravelFlare\Recorders\RoutingRecorder;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Events\Routing;
use Illuminate\Routing\Route;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Recorders\RoutingRecorder\RoutingRecorder as BaseRoutingRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelRouteAttributesProvider;
use Spatie\LaravelFlare\Facades\Flare;

class RoutingRecorder extends BaseRoutingRecorder
{
    protected ?Route $matchedRoute = null;

    protected ?Request $matchedRequest = null;

    public static function type(): string|RecorderType
    {
        return RecorderType::Routing;
    }

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            $container->get(Tracer::class),
            $container->get(EntryPointResolver::class),
            $container->get(Lifecycle::class),
            $container->get(Application::class),
            $container->get(Dispatcher::class),
            $container->get(BackTracer::class),
            $config,
        );
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
            $this->matchedRoute = $event->route;
            $this->matchedRequest = $event->request;

            $this->recordRoutingEnd(
                new LaravelRouteAttributesProvider($event->route, $event->request->getMethod()),
            );

            $this->recordBeforeMiddlewareStart();
        });

        $this->dispatcher->listen(RequestHandled::class, function () {
            if (! $this->tracer->isSampling()) {
                return;
            }

            // In some cases when an error happens in one of these stages (or an abort is thrown) the only event to catch this is the RequestHandled event.
            Flare::routing()?->recordGlobalBeforeMiddlewareEnd();
            Flare::routing()?->recordBeforeMiddlewareEnd();
            $this->recordForcedRoutingEnd();

            Flare::response()?->recordEnd();
        });
    }
}

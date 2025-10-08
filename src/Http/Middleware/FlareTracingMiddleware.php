<?php

namespace Spatie\LaravelFlare\Http\Middleware;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Spatie\LaravelFlare\Facades\Flare;
use Symfony\Component\HttpFoundation\Response;

class FlareTracingMiddleware
{
    public function __construct(
        protected Tracer $tracer,
        protected Application $app,
        protected LaravelRequestAttributesProvider $attributesProvider,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $ignorePaths = [
            '_debugbar',
            'telescope',
            'horizon',
        ];

        if (Str::startsWith($request->decodedPath(), $ignorePaths)) {
            $this->tracer->trashCurrentTrace();

            return $next($request);
        }

        Flare::request()?->recordStart($request);
        Flare::routing()?->recordGlobalBeforeMiddlewareStart();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        Flare::application()->recordTerminating();
        Flare::request()?->recordEnd(
            responseStatusCode: $response->getStatusCode(),
            attributes: $this->attributesProvider->toArray($request, includeContents: false)
        );
    }
}

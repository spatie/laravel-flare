<?php

namespace Spatie\LaravelFlare\Http\Middleware;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Spatie\LaravelFlare\Enums\SpanType as LaravelSpanType;
use Spatie\LaravelFlare\Facades\Flare;
use Symfony\Component\HttpFoundation\Response;

class FlareTracingMiddleware
{
    protected Span $requestSpan;

    public function __construct(
        protected Tracer $tracer,
        protected Application $app,
        protected LaravelRequestAttributesProvider $attributesProvider,
        protected bool $traceGlobalMiddleware = true,
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

        $this->startTrace($request);

        return $next($request);
    }

    protected function startTrace(Request $request): void
    {
        $attributes = [
            'flare.span_type' => SpanType::Request,
            'http.request.method' => strtoupper($request->getMethod()),
        ];

        $requestSpan = $this->tracer->startSpan(
            name: "request - ".$request->url(),
            attributes: $attributes
        );

        if($requestSpan === null){
            return; // We did not start sampling
        }

        $this->requestSpan = $requestSpan;

        if ($this->traceGlobalMiddleware) {
            $this->tracer->startSpan(
                name: "Middleware (global, before)",
                attributes: [
                    'flare.span_type' => LaravelSpanType::GlobalMiddlewareBefore,
                ],
            );
        }
    }

    public function terminate(Request $request, Response $response): void
    {
        Flare::application()->recordTerminated();

        if (! $this->tracer->hasCurrentSpan(SpanType::Request)) {
            return;
        }

        $this->requestSpan->addAttributes(
            $this->attributesProvider->toArray($request, includeContents: false)
        );

        $this->requestSpan->addAttribute('http.response.status_code', $response->getStatusCode());

        $requestSpan = $this->tracer->endSpan();

        if ($requestSpan->parentSpanId === null) {
            // In case of Octane
            $this->tracer->endTrace();
        }
    }
}

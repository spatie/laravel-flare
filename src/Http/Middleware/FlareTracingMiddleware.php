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

        $this->tracer->potentialStartTrace(); // In case of Octane

        if ($this->tracer->isSampling()) {
            $this->startTrace($request);
        }

        return $next($request);
    }

    protected function startTrace(Request $request): void
    {
        $attributes = [
            'flare.span_type' => SpanType::Request,
            'http.request.method' => strtoupper($request->getMethod()),
        ];

        $this->requestSpan = Span::build(
            traceId: $this->tracer->currentTraceId(),
            parentId: $this->tracer->currentSpanId(),
            name: "request - ".$request->url(),
            attributes: $attributes
        );

        $this->tracer->addSpan($this->requestSpan, makeCurrent: true);

        if ($this->traceGlobalMiddleware) {
            $this->tracer->addSpan(
                Span::build(
                    traceId: $this->tracer->currentTraceId(),
                    parentId: $this->tracer->currentSpanId(),
                    name: "Middleware (global, before)",
                    attributes: [
                        'flare.span_type' => LaravelSpanType::GlobalMiddlewareBefore,
                    ]
                ),
                makeCurrent: true
            );
        }
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->tracer->hasCurrentSpan(LaravelSpanType::Terminating)) {
            $this->tracer->endSpan();
        }

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

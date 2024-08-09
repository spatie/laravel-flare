<?php

namespace Spatie\LaravelFlare\Http\Middleware;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\LaravelFlare\FlareConfig;
use Symfony\Component\HttpFoundation\Response;

class FlareTracingMiddleware
{
    protected Span $span;

    public function __construct(
        protected Tracer $tracer,
        protected Application $app
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $this->tracer->potentialStartTrace([]);

        if ($this->tracer->isSamping()) {
            $this->startTrace($request);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! isset($this->span)) {
            return;
        }

        $flareConfig = $this->app->make(FlareConfig::class);

        $attributesProvider = (new LaravelRequestAttributesProvider(
        // TODO: these should be stored on the config object and not in the middleware
        ));

        $this->span->addAttributes($attributesProvider->toArray($request));
        $this->span->addAttribute('http.response.status_code', $response->getStatusCode());

        $this->tracer->endCurrentSpan();
        $this->tracer->endTrace();
    }

    private function startTrace(Request $request): void
    {
        $start = (defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT')) * 1000_000;

        $attributes = [
            'flare.span_type' => SpanType::Request,
            'http.request.method' => strtoupper($request->getMethod()),
        ];

        $this->span = Span::build(
            traceId: $this->tracer->currentTraceId(),
            name: "request - ".$request->url(),
            startUs: $start,
            attributes: $attributes
        );

        $this->tracer->addSpan($this->span, makeCurrent: true);
    }
}

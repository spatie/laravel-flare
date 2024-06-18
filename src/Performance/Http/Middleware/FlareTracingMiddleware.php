<?php

namespace Spatie\LaravelFlare\Performance\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Spatie\FlareClient\Performance\Sampling\Sampler;
use Spatie\FlareClient\Performance\Sampling\SamplingContext;
use Spatie\FlareClient\Performance\Spans\Span;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\LaravelFlare\Performance\Enums\SpanType;
use Symfony\Component\HttpFoundation\Response;

class FlareTracingMiddleware
{
    protected Span $span;

    private Tracer $tracer;

    public function __construct(
        protected Application $app
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $shouldSample = $this->app->make(Sampler::class)->shouldSample(
            new SamplingContext()
        );

        if ($shouldSample) {
            $this->tracer = $this->app->make(Tracer::class);

            $this->startTrace($request);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! isset($this->span)) {
            return;
        }

        $this->addRouteAttributes($request);
        $this->addUserAttributes($request);

        $this->span->addAttribute('http.response.status_code', $response->getStatusCode());

        $this->tracer->endCurrentSpan();
        $this->tracer->endCurrentTrace();
    }

    private function startTrace(Request $request): void
    {
        $this->tracer->startTrace();

        $start = (defined('LARAVEL_START') ? LARAVEL_START : $request->server('REQUEST_TIME_FLOAT')) * 1000_000;

        $attributes = [
            // TODO: check these with the official otel doc
            'flare.span_type' => SpanType::Request,
            'http.request.method' => strtoupper($request->getMethod()),
            'network.protocol.version' => $request->server('SERVER_PROTOCOL'),
            'request.path' => $request->path(),
            'request.query' => $request->getQueryString(),
            'server.address' => empty($request->server('SERVER_NAME'))
                ? $request->server('SERVER_ADDR')
                : $request->server('SERVER_NAME'),
            'server.port' => $request->server('SERVER_PORT'),
            'url.scheme' => $request->getScheme(),
            'client.address' => $request->getClientIp(),
            'user_agent' => $request->userAgent(),
        ];

        $this->span = Span::build(
            traceId: $this->tracer->currentTraceId(),
            name: "request - ".$request->url(),
            startUs: $start,
            attributes: $attributes
        );

        $this->tracer->addSpan($this->span, makeCurrent: true);
    }

    private function addRouteAttributes(Request $request): void
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return;
        }

        $this->span->addAttribute('laravel.route.uri', $route->uri());

        if ($route->getName()) {
            $this->span->addAttribute('laravel.route.name', $route->getName());
        }

        $this->span->rename("request - ".$route->uri());
    }

    private function addUserAttributes(Request $request): void
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            return;
        }

        $this->span->addAttribute('laravel.user.id', $user->getKey());
        $this->span->addAttribute('laravel.user.email', $user->getAttribute('email'));
    }
}

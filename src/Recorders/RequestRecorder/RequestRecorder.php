<?php

namespace Spatie\LaravelFlare\Recorders\RequestRecorder;

use Illuminate\Http\Request as LaravelRequest;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\RequestRecorder\RequestRecorder as BaseRequestRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Symfony\Component\HttpFoundation\Request;

class RequestRecorder extends BaseRequestRecorder
{
    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        array $config,
        LaravelRequestAttributesProvider $requestAttributesProvider
    ) {
        parent::__construct($tracer, $backTracer, $config, $requestAttributesProvider);
    }

    public function recordStart(
        ?Request $request = null,
        ?string $route = null,
        ?string $entryPointClass = null,
        array $attributes = []
    ): ?Span {
        if (! $request instanceof LaravelRequest) {
            return null;
        }

        return $this->startSpan(nameAndAttributes: fn () => [
            'name' => "Request - {$request->url()}",
            'attributes' => [
                'flare.span_type' => SpanType::Request,
                'http.request.method' => strtoupper($request->getMethod()),
                ...$attributes,
            ],
        ], canStartTrace: true);
    }
}

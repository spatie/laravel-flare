<?php

namespace Spatie\LaravelFlare\Recorders\RequestRecorder;

use Illuminate\Http\Request as LaravelRequest;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\RequestRecorder\RequestRecorder as BaseRequestRecorder;
use Spatie\FlareClient\Spans\Span;
use Symfony\Component\HttpFoundation\Request;

class RequestRecorder extends BaseRequestRecorder
{
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
        ]);
    }
}

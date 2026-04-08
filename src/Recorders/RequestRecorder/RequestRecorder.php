<?php

namespace Spatie\LaravelFlare\Recorders\RequestRecorder;

use Illuminate\Http\Request as LaravelRequest;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\RequestRecorder\RequestRecorder as BaseRequestRecorder;
use Spatie\FlareClient\Spans\Span;
use Symfony\Component\HttpFoundation\Request;

class RequestRecorder extends BaseRequestRecorder
{
    public const DEFAULT_GROUP_UNMATCHED_ROUTE_ERRORS = true;

    protected bool $groupUnmatchedRouteErrors = self::DEFAULT_GROUP_UNMATCHED_ROUTE_ERRORS;

    protected function configure(array $config): void
    {
        parent::configure($config);

        $this->groupUnmatchedRouteErrors = $config['group_unmatched_route_errors'] ?? self::DEFAULT_GROUP_UNMATCHED_ROUTE_ERRORS;
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

    public function recordEnd(
        ?int $responseStatusCode = null,
        ?int $responseBodySize = null,
        array $attributes = [],
    ): ?Span {
        if (
            $this->groupUnmatchedRouteErrors
            && $responseStatusCode !== null
            && $responseStatusCode >= 400
            && $responseStatusCode < 500
            && ! array_key_exists('http.route', $attributes)
        ) {
            $attributes['http.route'] = "errors::{$responseStatusCode}";
        }

        return parent::recordEnd($responseStatusCode, $responseBodySize, $attributes);
    }
}

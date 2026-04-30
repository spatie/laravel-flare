<?php

namespace Spatie\LaravelFlare\Recorders\RequestRecorder;

use Spatie\FlareClient\Contracts\ResponseAttributesProvider;
use Spatie\FlareClient\Recorders\RequestRecorder\RequestRecorder as BaseRequestRecorder;
use Spatie\FlareClient\Spans\Span;

class RequestRecorder extends BaseRequestRecorder
{
    public const DEFAULT_GROUP_UNMATCHED_ROUTE_ERRORS = true;

    protected bool $groupUnmatchedRouteErrors = self::DEFAULT_GROUP_UNMATCHED_ROUTE_ERRORS;

    protected function configure(array $config): void
    {
        parent::configure($config);

        $this->groupUnmatchedRouteErrors = $config['group_unmatched_route_errors'] ?? self::DEFAULT_GROUP_UNMATCHED_ROUTE_ERRORS;
    }

    public function recordEnd(
        ?ResponseAttributesProvider $response = null,
        array $attributes = [],
    ): ?Span {
        $statusCode = $response?->statusCode();

        if (
            $this->groupUnmatchedRouteErrors
            && $statusCode !== null
            && $statusCode >= 400
            && $statusCode < 500
            && ! array_key_exists('http.route', $attributes)
        ) {
            $attributes['http.route'] = "errors::{$statusCode}";
        }

        return parent::recordEnd($response, $attributes);
    }
}

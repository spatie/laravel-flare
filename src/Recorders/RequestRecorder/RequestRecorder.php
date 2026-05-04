<?php

namespace Spatie\LaravelFlare\Recorders\RequestRecorder;

use Spatie\FlareClient\AttributesProviders\UserAttributesProvider;
use Spatie\FlareClient\Contracts\RequestAttributesProvider;
use Spatie\FlareClient\Contracts\ResponseAttributesProvider;
use Spatie\FlareClient\Contracts\RouteAttributesProvider;
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
        ?RequestAttributesProvider $requestAttributesProvider = null,
        ?ResponseAttributesProvider $responseAttributesProvider = null,
        ?RouteAttributesProvider $routeAttributesProvider = null,
        ?UserAttributesProvider $userAttributesProvider = null,
        array $attributes = [],
    ): ?Span {
        $statusCode = $responseAttributesProvider?->statusCode();

        if (
            $this->groupUnmatchedRouteErrors
            && $statusCode !== null
            && $statusCode >= 400
            && $statusCode < 500
            && $routeAttributesProvider?->route() === null
            && ! array_key_exists('http.route', $attributes)
        ) {
            $attributes['http.route'] = "errors::{$statusCode}";
        }

        return parent::recordEnd(
            requestAttributesProvider: $requestAttributesProvider,
            responseAttributesProvider: $responseAttributesProvider,
            routeAttributesProvider: $routeAttributesProvider,
            userAttributesProvider: $userAttributesProvider,
            attributes: $attributes,
        );
    }

    protected function defaultIgnoredPaths(): array
    {
        return [
            '/_debugbar*',
            '/telescope*',
            '/horizon*',
            '/livewire/livewire.js',
            '/livewire/livewire.min.js',
            '/livewire-*/livewire.js',
            '/livewire-*/livewire.min.js',
        ];
    }
}

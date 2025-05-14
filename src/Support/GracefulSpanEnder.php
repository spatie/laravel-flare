<?php

namespace Spatie\LaravelFlare\Support;

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\GracefulSpanEnder as BaseGracefulSpanEnder;
use Spatie\LaravelFlare\Enums\SpanType as LaravelSpanType;

class GracefulSpanEnder extends BaseGracefulSpanEnder
{
    public function shouldGracefullyEndSpan(Span $span): bool
    {
        /** @var SpanType|LaravelSpanType|string|null $type */
        $type = $span->attributes['flare.span_type'] ?? null;

        if ($type === null) {
            return true;
        }

        $shouldNotEnd = $type === SpanType::Application
            || $type === SpanType::Request;

        return $shouldNotEnd === false;
    }
}

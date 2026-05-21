<?php

namespace Spatie\LaravelFlare\Sampling;

use InvalidArgumentException;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Sampling\DeferredSamplerRule;
use Spatie\FlareClient\Sampling\SamplingRule;
use Spatie\FlareClient\Support\PatternMatcher;

class RouteNameSamplingRule extends SamplingRule implements DeferredSamplerRule
{
    public function __construct(
        protected string $pattern,
        protected float $rate,
    ) {
        if ($rate < 0 || $rate > 1) {
            throw new InvalidArgumentException('Sampling rate must be between 0 and 1.');
        }
    }

    public function appliesTo(EntryPointType $entryPointType): bool
    {
        return $entryPointType === EntryPointType::Web;
    }

    public function getMatchedRate(EntryPoint $entryPoint): ?float
    {
        $value = $entryPoint->samplingAttributes['laravel.route.name'] ?? null;

        if ($value === null) {
            return null;
        }

        return PatternMatcher::matches($value, $this->pattern) ? $this->rate : null;
    }
}

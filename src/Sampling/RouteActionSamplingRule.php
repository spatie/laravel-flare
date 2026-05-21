<?php

namespace Spatie\LaravelFlare\Sampling;

use InvalidArgumentException;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Sampling\DeferredSamplerRule;
use Spatie\FlareClient\Sampling\SamplingRule;
use Spatie\FlareClient\Support\PatternMatcher;

class RouteActionSamplingRule extends SamplingRule implements DeferredSamplerRule
{
    protected string $pattern;

    /**
     * @param  string|array{class-string, string}  $pattern
     */
    public function __construct(
        string|array $pattern,
        protected float $rate,
    ) {
        if ($rate < 0 || $rate > 1) {
            throw new InvalidArgumentException('Sampling rate must be between 0 and 1.');
        }

        $this->pattern = is_array($pattern) ? "{$pattern[0]}@{$pattern[1]}" : $pattern;
    }

    public function appliesTo(EntryPointType $entryPointType): bool
    {
        return $entryPointType === EntryPointType::Web;
    }

    public function getMatchedRate(EntryPoint $entryPoint): ?float
    {
        $value = $entryPoint->samplingAttributes['laravel.route.action'] ?? null;

        if ($value === null) {
            return null;
        }

        return PatternMatcher::matches($value, $this->pattern) ? $this->rate : null;
    }
}

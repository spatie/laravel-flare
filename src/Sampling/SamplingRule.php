<?php

namespace Spatie\LaravelFlare\Sampling;

use InvalidArgumentException;
use Spatie\FlareClient\Sampling\SamplingRule as BaseSamplingRule;

abstract class SamplingRule extends BaseSamplingRule
{
    public static function forRouteName(string $pattern, float $rate): RouteNameSamplingRule
    {
        return new RouteNameSamplingRule($pattern, $rate);
    }

    public static function forRouteAction(string $pattern, float $rate): RouteActionSamplingRule
    {
        return new RouteActionSamplingRule($pattern, $rate);
    }

    /**
     * Hydrate an array-form rule (as written in config/flare.php) into a typed rule.
     * The `type` field is the FQCN of a SamplingRule subclass.
     *
     * @param  array{type: class-string<BaseSamplingRule>, pattern: string, rate: float}  $data
     */
    public static function fromArray(array $data): BaseSamplingRule
    {
        if (! isset($data['type'], $data['pattern'], $data['rate'])) {
            throw new InvalidArgumentException('Sampling rule array must contain "type", "pattern", and "rate" keys.');
        }

        $class = $data['type'];

        if (! is_string($class) || ! is_a($class, BaseSamplingRule::class, true)) {
            throw new InvalidArgumentException('Sampling rule "type" must reference a SamplingRule subclass.');
        }

        return new $class($data['pattern'], $data['rate']);
    }
}

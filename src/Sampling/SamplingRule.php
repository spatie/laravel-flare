<?php

namespace Spatie\LaravelFlare\Sampling;

use Spatie\FlareClient\Sampling\SamplingRule as BaseSamplingRule;

abstract class SamplingRule extends BaseSamplingRule
{
    public static function forRouteName(string $pattern, float $rate): RouteNameSamplingRule
    {
        return new RouteNameSamplingRule($pattern, $rate);
    }

    /**
     * @param  string|array{class-string, string}  $pattern
     */
    public static function forRouteAction(string|array $pattern, float $rate): RouteActionSamplingRule
    {
        return new RouteActionSamplingRule($pattern, $rate);
    }

    public static function forQueueName(string $pattern, float $rate): QueueNameSamplingRule
    {
        return new QueueNameSamplingRule($pattern, $rate);
    }

    public static function forQueueConnection(string $pattern, float $rate): QueueConnectionSamplingRule
    {
        return new QueueConnectionSamplingRule($pattern, $rate);
    }

    /**
     * @param  array{type: class-string<BaseSamplingRule>, pattern: string|array{class-string, string}, rate: float}  $data
     */
    public static function fromArray(array $data): ?BaseSamplingRule
    {
        if (! isset($data['type'], $data['pattern'], $data['rate'])) {
            return null;
        }

        $class = $data['type'];

        if (! is_string($class) || ! is_a($class, BaseSamplingRule::class, true)) {
            return null;
        }

        return new $class($data['pattern'], $data['rate']);
    }
}

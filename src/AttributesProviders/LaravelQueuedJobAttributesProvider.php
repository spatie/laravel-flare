<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Spatie\Backtrace\Arguments\ReduceArgumentPayloadAction;
use Spatie\FlareClient\Contracts\JobAttributesProvider;
use Spatie\LaravelFlare\AttributesProviders\Concerns\ResolvesJobPayloadAttributes;

class LaravelQueuedJobAttributesProvider implements JobAttributesProvider
{
    use ResolvesJobPayloadAttributes;

    /** @var array<string, mixed> */
    protected array $payloadAttributes;

    /** @param array<string, mixed> $payload */
    public function __construct(
        protected ReduceArgumentPayloadAction $reduceArgumentPayloadAction,
        protected array $payload,
        protected ?string $connectionName = null,
        protected ?string $queueName = null,
        protected int $maxChainedJobReportingDepth = 3,
    ) {
        $this->payloadAttributes = $this->jobPropertiesFromPayload($payload);
    }

    public function toArray(): array
    {
        return [
            'laravel.job.queue.connection_name' => $this->connectionName,
            'laravel.job.queue.name' => $this->queueName,
            ...$this->payloadAttributes,
        ];
    }

    public function jobName(): string
    {
        return $this->payloadAttributes['laravel.job.name']
            ?? $this->payloadAttributes['laravel.job.class']
            ?? $this->payload['displayName']
            ?? 'Unknown';
    }

    public function jobClass(): ?string
    {
        return $this->payloadAttributes['laravel.job.class'] ?? null;
    }

    public function isBatched(): bool
    {
        return array_key_exists('laravel.job.batch_id', $this->payloadAttributes);
    }
}

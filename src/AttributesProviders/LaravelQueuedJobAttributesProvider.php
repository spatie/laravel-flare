<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Spatie\FlareClient\Contracts\QueuedJobAttributesProvider;

class LaravelQueuedJobAttributesProvider implements QueuedJobAttributesProvider
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        protected array $payload,
        protected ?string $connectionName = null,
        protected ?string $queueName = null,
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'laravel.job.name' => $this->jobName(),
            'laravel.job.class' => $this->jobClass(),
            'laravel.job.queue.connection_name' => $this->connectionName,
            'laravel.job.queue.name' => $this->queueName,
        ], fn ($value) => $value !== null);
    }

    public function jobName(): string
    {
        return $this->payload['displayName'] ?? 'Unknown';
    }

    public function jobClass(): ?string
    {
        $command = $this->payload['data']['commandName'] ?? null;

        return match (true) {
            is_string($command) => $command,
            is_object($command) => $command::class,
            default => null,
        };
    }

    public function isBatched(): bool
    {
        if (! empty($this->payload['data']['batchId'])) {
            return true;
        }

        $command = $this->payload['data']['command'] ?? null;

        return is_object($command) && ! empty($command->batchId ?? null);
    }
}

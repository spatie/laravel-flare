<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\RedisJob;
use Spatie\Backtrace\Arguments\ReduceArgumentPayloadAction;
use Spatie\FlareClient\Contracts\EntryPointHandlerProvider;
use Spatie\FlareClient\Contracts\JobAttributesProvider;
use Spatie\LaravelFlare\AttributesProviders\Concerns\ResolvesJobPayloadAttributes;

class LaravelJobAttributesProvider implements JobAttributesProvider, EntryPointHandlerProvider
{
    use ResolvesJobPayloadAttributes;

    /** @var array<string, mixed>|null */
    protected ?array $resolvedPayload = null;

    public function __construct(
        protected ReduceArgumentPayloadAction $reduceArgumentPayloadAction,
        protected Job $job,
        protected ?string $connectionName = null,
        protected int $maxChainedJobReportingDepth = 3,
    ) {
    }

    public function toArray(): array
    {
        return array_merge(
            $this->jobPropertiesFromPayload($this->resolveJobPayload()),
            [
                'laravel.job.queue.connection_name' => $this->connectionName ?? $this->job->getConnectionName(),
                'laravel.job.queue.name' => $this->job->getQueue(),
            ]
        );
    }

    public function jobName(): string
    {
        return $this->resolveJobPayload()['displayName'] ?? 'unknown';
    }

    public function jobClass(): ?string
    {
        return $this->resolveJobPayload()['data']['commandName'] ?? null;
    }

    public function entryPointHandlerName(): ?string
    {
        return null;
    }

    public function entryPointHandlerType(): ?string
    {
        return 'laravel_job';
    }

    public function entryPointHandlerIdentifier(): ?string
    {
        return $this->jobName();
    }

    /** @return array<string, mixed> */
    protected function resolveJobPayload(): array
    {
        if ($this->resolvedPayload !== null) {
            return $this->resolvedPayload;
        }

        if (! $this->job instanceof RedisJob) {
            return $this->resolvedPayload = $this->job->payload();
        }

        try {
            return $this->resolvedPayload = json_decode($this->job->getReservedJob(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception) {
            return $this->resolvedPayload = $this->job->payload();
        }
    }
}

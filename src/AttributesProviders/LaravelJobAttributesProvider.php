<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Error;
use Exception;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Spatie\Backtrace\Arguments\ReduceArgumentPayloadAction;
use Spatie\FlareClient\Time\TimeHelper;

class LaravelJobAttributesProvider
{
    public function __construct(
        protected ReduceArgumentPayloadAction $reduceArgumentPayloadAction,
    ) {
    }

    public function toArray(
        Job $job,
        ?string $connectionName = null,
        int $maxChainedJobReportingDepth = 3
    ): array {
        return array_merge(
            $this->getJobPropertiesFromPayload($this->resolveJobPayload($job), $maxChainedJobReportingDepth),
            [
                'laravel.job.queue.connection_name' => $connectionName ?? $job->getConnectionName(),
                'laravel.job.queue.name' => $job->getQueue(),
            ]
        );
    }

    public function getJobPropertiesFromPayload(
        array $payload,
        int $maxChainedJobReportingDepth = 3,
    ): array {
        // Queue::createObjectPayload() is used to create the payload for the job

        try {
            if (is_string($payload['data'])) {
                $payload['data'] = json_decode($payload['data'], true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (Exception) {
        }

        $attributes = [];

        if ($jobClass = $payload['data']['commandName'] ?? null) {
            $attributes['laravel.job.class'] = $jobClass;
        }

        if (array_key_exists('uuid', $payload)) {
            $attributes['laravel.job.uuid'] = $payload['uuid'];
        }

        if (array_key_exists('displayName', $payload)) {
            $attributes['laravel.job.name'] = $payload['displayName'];
        }

        if (array_key_exists('maxTries', $payload)) {
            $attributes['laravel.job.max_tries'] = $payload['maxTries'];
        }

        if (array_key_exists('maxExceptions', $payload)) {
            $attributes['laravel.job.max_exceptions'] = $payload['maxExceptions'];
        }

        if (array_key_exists('failOnTimeout', $payload)) {
            $attributes['laravel.job.fail_on_timeout'] = $payload['failOnTimeout'];
        }

        if (array_key_exists('backoff', $payload)) {
            $attributes['laravel.job.backoff'] = $payload['backoff'];
        }

        if (array_key_exists('timeout', $payload)) {
            $attributes['laravel.job.timeout'] = $payload['timeout'];
        }

        if (array_key_exists('retryUntil', $payload) && $payload['retryUntil'] !== null) {
            $attributes['laravel.job.retry_until'] = TimeHelper::seconds($payload['retryUntil']);
        }

        if (array_key_exists('pushedAt', $payload) && $payload['pushedAt'] !== null) {
            try {
                $pushedAt = str_contains($payload['pushedAt'], '.')
                    ? CarbonImmutable::createFromFormat('U.u', $payload['pushedAt'])
                    : CarbonImmutable::createFromFormat('U', $payload['pushedAt']);
            } catch (InvalidFormatException) {
                $pushedAt = null;
            }

            if ($pushedAt) {
                $attributes['laravel.job.pushed_at'] = $pushedAt->timestamp * 1_000_000_000 + $pushedAt->micro * 1_000;
            }
        }

        if (array_key_exists('attempts', $payload)) {
            $attributes['laravel.job.attempts'] = $payload['attempts'];
        }

        if (array_key_exists('tags', $payload)) {
            $attributes['laravel.job.tags'] = $payload['tags'];
        }

        $propertyAttributes = [];

        try {
            $command = is_string($payload['data']['command'])
                ? $this->resolveObjectFromCommand($payload['data']['command'])
                : $payload['data']['command'];

            $propertyAttributes = $this->resolveCommandProperties(
                $command,
                $maxChainedJobReportingDepth
            );
        } catch (Exception $exception) {
        }

        return array_merge(
            array_filter($attributes),
            $propertyAttributes
        );
    }

    protected function resolveJobPayload(Job $job): array
    {
        if (! $job instanceof RedisJob) {
            return $job->payload();
        }

        try {
            return json_decode($job->getReservedJob(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return $job->payload();
        }
    }

    protected function resolveCommandProperties(object $command, int $maxChainDepth): array
    {
        $jobProperties = collect((new ReflectionClass($command))->getProperties())
            ->mapWithKeys(function (ReflectionProperty $property) use ($command) {
                try {
                    $property->setAccessible(true);

                    return [$property->name => $property->getValue($command)];
                } catch (Error $error) {
                    return [];
                }
            });

        $attributes = [];

        if ($jobProperties->get('after_commit') !== null) {
            $attributes['laravel.job.after_commit'] = $jobProperties->get('after_commit');
        }

        if ($jobProperties->get('delay') !== null) {
            $attributes['laravel.job.delay'] = $jobProperties->get('delay');
        }

        if ($jobProperties->get('connection') !== null) {
            $attributes['laravel.job.queue.connection_name'] = $jobProperties->get('connection');
        }

        if ($jobProperties->get('chainConnection') !== null) {
            $attributes['laravel.job.chain.queue.connection_name'] = $jobProperties->get('chainConnection');
        }

        if ($jobProperties->get('chainQueue') !== null) {
            $attributes['laravel.job.chain.queue.name'] = $jobProperties->get('chainQueue');
        }

        if ($jobProperties->get('batchId') !== null) {
            $attributes['laravel.job.batch_id'] = $jobProperties->get('batchId');
        }

        if ($jobProperties->get('deleteWhenMissingModels') !== null) {
            $attributes['laravel.job.delete_when_missing_models'] = $jobProperties->get('deleteWhenMissingModels');
        }

        $propertiesToIgnore = [
            'job',
            'closure',
            'fakeBatch',
            'connection',
            'queue',
            'delay',
            'afterCommit',
            'middleware',
            'chained',
            'chainConnection',
            'chainQueue',
            'chainCatchCallbacks',
            'batchId',
            'failureCallbacks',
            'deleteWhenMissingModels',
            'messageGroup',
            'deduplicator',
            'tries',
        ];

        $properties = $jobProperties
            ->reject(fn (mixed $value, string $name) => in_array($name, $propertiesToIgnore))
            ->map(fn (mixed $value) => $this->reduceArgumentPayloadAction->reduce($value)->value);

        $chain = $jobProperties->has('chained')
            ? $this->resolveJobChain($jobProperties->get('chained'), $maxChainDepth)
            : [];

        if ($properties->isNotEmpty()) {
            $attributes['laravel.job.properties'] = $properties->all();
        }

        if (! empty($chain)) {
            $attributes['laravel.job.chain.jobs'] = $chain;
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $chainedCommands
     * @param int $maxDepth
     *
     * @return array
     */
    protected function resolveJobChain(array $chainedCommands, int $maxDepth): array
    {
        if ($maxDepth === 0) {
            return [];
        }

        return array_map(
            function (string $command) use ($maxDepth) {
                $commandObject = $this->resolveObjectFromCommand($command);

                $attributes = [
                    'laravel.job.class' => get_class($commandObject),
                ];

                if (
                    method_exists($commandObject, 'displayName')
                    && $commandObject->displayName() !== $attributes['laravel.job.class']
                ) {
                    $attributes['laravel.job.name'] = $commandObject->displayName();
                }

                return array_merge(
                    $attributes,
                    $this->resolveCommandProperties($commandObject, $maxDepth - 1)
                );
            },
            $chainedCommands
        );
    }

    // Taken from Illuminate\Queue\CallQueuedHandler
    protected function resolveObjectFromCommand(string $command): object
    {
        if (Str::startsWith($command, 'O:')) {
            return unserialize($command);
        }

        $app = app();

        if ($app->bound(Encrypter::class)) {
            return unserialize($app[Encrypter::class]->decrypt($command));
        }

        throw new RuntimeException('Unable to extract job payload.');
    }
}

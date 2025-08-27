<?php

namespace Spatie\LaravelFlare\Senders;

use Closure;
use Spatie\FlareClient\Enums\FlarePayloadType;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;
use Spatie\LaravelFlare\Jobs\SendFlarePayload;

class LaravelVaporSender implements Sender
{
    protected static Sender $senderInstance;

    /** @var class-string<Sender> */
    protected string $sender;

    protected array $senderConfig;

    protected bool $queueTraces;

    protected bool $queueErrors;

    private ?string $queue;

    private ?string $connection;

    public function __construct(
        protected array $config = []
    ) {
        $this->sender = $this->config['sender'] ?? LaravelHttpSender::class;
        $this->senderConfig = $this->config['sender_config'] ?? [];
        $this->queueTraces = $this->config['queue_traces'] ?? true;
        $this->queueErrors = $this->config['queue_errors'] ?? false;
        $this->queue = $this->config['queue'] ?? null;
        $this->connection = $this->config['connection'] ?? null;
    }

    public function post(string $endpoint, string $apiToken, array $payload, FlarePayloadType $type, Closure $callback): void
    {
        $shouldQueue = match ($type) {
            FlarePayloadType::TestError => true,
            FlarePayloadType::Error => $this->queueErrors,
            FlarePayloadType::Traces => $this->queueTraces
        };

        if (app()->runningInConsole()) {
            $shouldQueue = false;
        }

        if (! $shouldQueue) {
            $this->resolveSender()->post(
                $endpoint,
                $apiToken,
                $payload,
                $type,
                $callback
            );

            return;
        }

        $job = new SendFlarePayload(
            $this->sender,
            $this->senderConfig,
            $endpoint,
            $payload,
            $type
        );

        if ($this->connection) {
            $job->onConnection($this->connection);
        }

        if ($this->queue) {
            $job->onQueue($this->queue);
        }

        dispatch($job);

        $callback(new Response(200, ['queued' => true]));
    }

    protected function resolveSender(): Sender
    {
        return static::$senderInstance ??= new ($this->sender)($this->senderConfig);
    }
}

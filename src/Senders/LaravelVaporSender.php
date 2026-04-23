<?php

namespace Spatie\LaravelFlare\Senders;

use Closure;
use Spatie\FlareClient\Enums\FlareEntityType;
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

    protected bool $queueLogs;

    protected bool $queueErrors;

    private ?string $queue;

    private ?string $connection;

    public function __construct(
        protected array $config = []
    ) {
        $this->sender = $this->config['sender'] ?? LaravelHttpSender::class;
        $this->senderConfig = $this->config['sender_config'] ?? [];
        $this->queueTraces = $this->config['queue_traces'] ?? true;
        $this->queueLogs = $this->config['queue_logs'] ?? true;
        $this->queueErrors = $this->config['queue_errors'] ?? false;
        $this->queue = $this->config['queue'] ?? null;
        $this->connection = $this->config['connection'] ?? null;
    }

    public function post(string $endpoint, string $apiKey, array $payload, FlareEntityType $type, bool $test, Closure $callback): void
    {
        $shouldQueue = match (true) {
            $test => false,
            $type === FlareEntityType::Errors => $this->queueErrors,
            $type === FlareEntityType::Traces => $this->queueTraces,
            $type === FlareEntityType::Logs => $this->queueLogs,
        };

        if (app()->runningInConsole()) {
            $shouldQueue = false;
        }

        if (! $shouldQueue) {
            $this->resolveSender()->post(
                $endpoint,
                $apiKey,
                $payload,
                $type,
                $test,
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

        $callback(new Response(200, []));
    }

    protected function resolveSender(): Sender
    {
        return static::$senderInstance ??= new ($this->sender)($this->senderConfig);
    }
}

<?php

namespace Spatie\LaravelFlare\Recorders\RedisCommandRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Redis\RedisManager;
use Spatie\FlareClient\Recorders\RedisCommandRecorder\RedisCommandRecorder as BaseRedisCommandRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\FlareClient\Tracer;

class RedisCommandRecorder extends BaseRedisCommandRecorder
{
    /**
     * @var array<string, array{host: string, port: string, database:string}>
     */
    protected array $resolvedConnections = [];

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        protected RedisManager $redisManager,
        array $config
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public function start(): void
    {
        if ($this->trace === false && $this->report === false) {
            return;
        }

        $this->resolveConnections(config('database.redis'));

        // TODO: maybe we should disable this by default?
        // Probably this is disabled by default because it's not a good idea to enable it by default
        $this->redisManager->enableEvents();

        $this->dispatcher->listen(CommandExecuted::class, function (CommandExecuted $event) {
            $connection = $this->resolvedConnections[$event->connectionName] ?? null;

            $this->record(
                command: $event->command,
                parameters: $event->parameters,
                duration: TimeHelper::milliseconds($event->time),
                namespace: $connection['database'] ?? null,
                serverAddress: $connection['host'] ?? null,
                serverPort: $connection['port'] ?? null,
                attributes: [
                    'laravel.db.connection' => $event->connectionName,
                ]
            );
        });
    }

    protected function resolveConnections(array $redisConfig): void
    {
        foreach ($redisConfig as $connection => $config) {
            if ($connection === 'clusters') {
                $this->resolveConnections($redisConfig);

                continue;
            }

            if (! is_array($config)
                || ! array_key_exists('host', $config)
                || ! array_key_exists('port', $config)
                || ! array_key_exists('database', $config)
            ) {
                continue;
            }

            $this->resolvedConnections[$connection] = [
                'host' => $config['host'],
                'port' => $config['port'],
                'database' => $config['database'],
            ];
        }
    }
}

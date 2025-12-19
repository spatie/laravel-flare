<?php

namespace Spatie\LaravelFlare\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;

class SendFlarePayload implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param class-string<Sender> $sender
     * @param array<string, mixed> $senderConfig
     * @param array<string, mixed> $payload
     */
    public function __construct(
        protected string $sender,
        protected array $senderConfig,
        protected string $endpoint,
        protected array $payload,
        protected FlareEntityType $type,
    ) {
    }

    public function handle(
        Repository $config,
    ): void {
        /** @var Sender $sender */
        $sender = new ($this->sender)($this->senderConfig);

        $sender->post(
            $this->endpoint,
            $config->get('flare.key'),
            $this->payload,
            $this->type,
            test: false,
            callback: function (Response $response) {
            }
        );
    }
}

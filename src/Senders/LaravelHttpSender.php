<?php

namespace Spatie\LaravelFlare\Senders;

use Closure;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Enums\FlarePayloadType;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;

class LaravelHttpSender implements Sender
{
    protected int $timeout;

    protected bool $async;

    public function __construct(
        protected array $config = []
    ) {
        $this->timeout = $this->config['timeout'] ?? 10;
    }

    public function post(string $endpoint, string $apiToken, array $payload, FlareEntityType $type, bool $test, Closure $callback): void
    {
        $response = Http::withHeader('x-api-token', $apiToken)
            ->timeout($this->timeout)
            ->post($endpoint, $payload);

        $this->handleResponse($response, $callback);
    }

    protected function handleResponse(
        HttpResponse $response,
        Closure $callback
    ): void {
        $callback(new Response(
            $response->status(),
            $response->json() ?? $response->body(),
        ));
    }
}

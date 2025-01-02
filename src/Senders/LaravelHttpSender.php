<?php

namespace Spatie\LaravelFlare\Senders;

use Closure;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
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
        $this->async = $this->config['async'] ?? true;
    }

    public function post(string $endpoint, string $apiToken, array $payload, Closure $callback): void
    {
        $response = Http::withHeader('x-api-token', $apiToken)
            ->timeout($this->timeout)
            ->async($this->async)
            ->post($endpoint, $payload);

        if ($this->async) {
            $response->then(fn (HttpResponse $response) => $this->handleResponse($response, $callback));

            return;
        }

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

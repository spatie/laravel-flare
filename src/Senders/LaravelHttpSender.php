<?php

namespace Spatie\LaravelFlare\Senders;

use Illuminate\Support\Facades\Http;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;

class LaravelHttpSender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload): Response
    {
        $response = Http::withHeader('X-Api-Key', $apiToken)
            ->withHeader('content-type', 'application/json')
            ->post($endpoint, $payload);

        return new Response(
            $response->status(),
            $response->json() ?? $response->body(),
        );
    }
}

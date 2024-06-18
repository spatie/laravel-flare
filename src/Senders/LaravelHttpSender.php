<?php

namespace Spatie\LaravelFlare\Senders;

use Illuminate\Support\Facades\Http;
use Spatie\FlareClient\Senders\Sender;

class LaravelHttpSender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload): array
    {
        return Http::withHeader('X-Api-Key', $apiToken)
            ->post($endpoint, $payload)
            ->throw()
            ->onError(function ($response) {
                // TODO: add a better handling here

                logger()->error('Failed to transmit traces', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            })->json();
    }
}

<?php

namespace Spatie\LaravelFlare\Senders;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Spatie\FlareClient\Senders\Sender;

class JaegerSender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload): array
    {
        if (! array_key_exists('resourceSpans', $payload)) {
            return [];
        }


        try {
            ray($payload);

            Http::withHeader('X-Api-Key', $apiToken)
                ->withHeader('Content-Type', 'application/json')
                ->post('http://localhost:4318/v1/traces', $payload)
                ->onError(function (Response $response) {
                    ray($response);
                    ray($response->body());
                })
                ->json();
        } catch (\Exception $e) {
            ray($e);
        }

        return [];
    }
}

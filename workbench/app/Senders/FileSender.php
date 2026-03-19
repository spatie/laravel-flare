<?php

namespace Workbench\App\Senders;

use Closure;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Senders\Sender;

class FileSender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload, FlareEntityType $type, bool $test, Closure $callback): void
    {
        $id = match ($type) {
            FlareEntityType::Errors => $payload['trackingUuid'],
            FlareEntityType::Traces => "{$payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['traceId']}-{$payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['spanId']}",
            FlareEntityType::Logs => uniqid(),
        };

        file_put_contents(
            storage_path("{$type->value}_{$id}.json"),
            json_encode($payload)
        );
    }
}

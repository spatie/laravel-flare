---
title: External HTTP requests 
---


Flare can collect information about the external HTTP requests that are being made from your application. This includes:

- The request method
- The request URL
- The request body size
- The request headers
- The response status code
- The response body size
- The response headers

This functionality is enabled by default, but you can disable it by ignoring the `ExternalHttp` collect in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::ExternalHttp],
),
```

You can configure the maximum number of external HTTP requests tracked while collecting data in the case of an error as follows:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::ExternalHttp->value => [
            'max_items_with_errors' => 10,
        ],
    ]
),
```

## Collecting Laravel's HTTP client requests

Requests made with the Laravel HTTP client are automatically collected by default; no additional setup is required.

## Collecting Guzzle HTTP requests

Flare can automatically collect Guzzle HTTP requests. This is done by using a middleware that will be added to the Guzzle client:

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Spatie\LaravelFlare\Recorders\ExternalHttpRecorder\Guzzle\FlareMiddleware;

$stack = HandlerStack::create();

$stack->push(new FlareMiddleware());

$client = new Client([
    'handler' => $stack,
]);
```

You can also use the `FlareHandlerStack`, which requires less code:

```php
use Spatie\LaravelFlare\Recorders\ExternalHttpRecorder\Guzzle\FlareHandlerStack;

$client = new Client([
    'handler' => new FlareHandlerStack()
]);
```

## Manually recording external HTTP requests

If you're making HTTP requests through a client that isn't automatically tracked, you can record them manually. The [PHP documentation](/docs/php/data-collection/external-http-requests) provides a full overview of all available recorder methods. When using these methods in Laravel, use the `Flare` facade instead of `$flare`:

```php
use Spatie\LaravelFlare\Facades\Flare;

Flare::externalHttp()->recordSending(
    url: 'https://example.com',
    method: 'POST',
);

// ... perform the request

Flare::externalHttp()->recordReceived(
    responseStatusCode: 200,
);
``` 

---
title: Laravel Vapor 
---

Flare will work out of the box with Laravel Vapor. However, there are a few things to keep in mind when using Flare with Vapor.

In normal server setups, Flare registers a hook into PHP to send all errors and traces at the end of the request. This ensures that your application will not be slowed down by sending errors and traces to Flare.

However, in a serverless environment like Vapor, the PHP process is frozen after the request has been handled. This means that the hook that Flare registers will not be called.

Laravel Vapor solves this by executing the hook before the request is finished, which can slow down your application quite a lot.

In order to solve this issue, you can configure Flare to send traces using a job on the queue instead of sending them at the end of the request. You can do this by changing the sender in your `config/flare.php` file:

```php
'sender' => [
    'class' => \Spatie\LaravelFlare\Senders\LaravelVaporSender::class,
    'config' => [],
],
```

By default, only traces will be sent using a job, errors will still be sent at the end of the request. If you want to send errors using a job as well, you can do so by adding the `queue_errors` key to the sender configuration:

```php
'sender' => [
    'class' => \Spatie\LaravelFlare\Senders\LaravelVaporSender::class,
    'config' => [
        'queue_errors' => true,
    ],
],
```

It is possible to define the connection and queue name that should be used to dispatch the job. You can do this by adding the `connection` and `queue` keys to the sender configuration:

```php
'sender' => [
    'class' => \Spatie\LaravelFlare\Senders\LaravelVaporSender::class,
    'config' => [
        'connection' => 'sqs',
        'queue' => 'flare',
    ],
],
```

If you want to configure the sender which will be called by the `LaravelVaporSender`, you can do so by adding the `sender` and `sender_config` keys to the sender configuration:

```php
'sender' => [
    'class' => \Spatie\LaravelFlare\Senders\LaravelVaporSender::class,
    'config' => [
        'sender' => \Spatie\FlareClient\Senders\CurlSender::class,
        'sender_config' => [
            'curl_options' => [
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
            ],
        ],
    ],
],
```

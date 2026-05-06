---
title: Logs 
---


Flare can collect information about the logs being written in your application. This includes:

- The log level
- The log message
- The log context

This functionality is enabled by default, but you can disable it by ignoring the `Logs` collect in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::Logs],
),
```

You can configure the maximum number of logs tracked while collecting data in the case of an error as follows:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Logs->value => [
            'max_items_with_errors' => 10,
        ],
    ]
),
```

The collection of logs will happen automatically.

## Manually recording logs

If you're writing log messages outside of Laravel's logging system, you can record them manually. The [PHP documentation](/docs/php/data-collection/logs) provides a full overview of all available recorder methods. When using these methods in Laravel, use the `Flare` facade instead of `$flare`:

```php
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\FlareClient\Enums\MessageLevels;

Flare::log()->record(
    message: 'This is a log message',
    level: MessageLevels::Debug,
    context: ['team_id' => 1],
);
``` 
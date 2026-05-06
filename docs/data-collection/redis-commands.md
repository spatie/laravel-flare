---
title: Redis commands 
---


Flare can collect information about the Redis commands being executed in your application. This includes:

- The command
- The command parameters
- The namespace (database)
- The Redis server IP & port

This functionality is **disabled** by default, but you can enable it by adding the `Redis` collect in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::RedisCommands->value => [
            'with_traces' => true,
            'with_errors' => true,
        ],
    ]
),
```

You can configure the maximum number of Redis commands tracked while collecting data in the case of an error as such:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::RedisCommands->value => [
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
        ],
    ]
),
```

## Manually recording Redis commands

If you're executing Redis commands outside of Laravel's Redis manager, you can record them manually. The [PHP documentation](/docs/php/data-collection/redis-commands) provides a full overview of all available recorder methods. When using these methods in Laravel, use the `Flare` facade instead of `$flare`:

```php
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\FlareClient\Time\TimeHelper;

Flare::redis()->record(
    command: 'SET',
    parameters: ['key', 'value'],
    duration: TimeHelper::microseconds(300),
);
```

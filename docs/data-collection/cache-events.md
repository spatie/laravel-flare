---
title: Cache events 
---


An application can use a cache to store data that is expensive to compute. Flare can collect information about the cache events in your application.

Flare will collect the following information:

- The cache key
- The cache store
- The cache operation (`Get`, `Set`, `Forget`)
- The cache result (`Hit`, `Miss`, `Success`, `Failure`)

This functionality is enabled by default, but you can disable it by ignoring the `Cache` collect in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::Cache],
),
```

It is possible to limit the amount of cache events tracked while collecting data in the case of an error, as such:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Cache->value => [
            'max_items_with_errors' => 50,
        ],
    ]
),
```

It is possible to limit the types of cache operations that are collected:

```php
use Spatie\FlareClient\Enums\CacheOperation;

'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Cache->value => [
            'operations' =>  [CacheOperation::Get]
        ],
    ]
),
```

## Manually recording cache events

If you're interacting with a cache that isn't managed by Laravel's cache system, you can record events manually. The [PHP documentation](/docs/php/data-collection/cache-events) provides a full overview of all available recorder methods. When using these methods in Laravel, use the `Flare` facade instead of `$flare`:

```php
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\Enums\CacheResult;

Flare::cache()->record(
    key: 'my-key',
    store: 'redis',
    operation: CacheOperation::Get,
    result: CacheResult::Hit,
);
``` 
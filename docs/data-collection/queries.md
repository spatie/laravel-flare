---
title: Queries 
---


Flare can collect information about the queries being executed in your application. This includes:

- The query
- The query bindings
- The database name
- The database driver
- The laravel connection

This functionality is enabled by default, but you can disable it by ignoring the `Queries` collect in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::Queries],
),
```

You can configure the maximum number of queries tracked while collecting data in the case of an error as such:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Queries->value => [
            'max_items_with_errors' => 10,
        ],
    ]
),
```

Collection of queries will happen automatically, but it is possible to configure the collection.

## Finding the origin

Database queries sometimes become a bit tricky to debug. You can find the origin of the query by setting the `findOrigin` parameter to `true`:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Queries->value => [
            'find_origin' => true,
        ],
    ]
),
```

Now, every query will include the file and line number where the query was executed.

If you only want to find the origins of slow queries, you can pass a `findOriginThreshold` parameter to the `collectQueries` method in milliseconds:

```php
use Spatie\FlareClient\Time\TimeHelper;

'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Queries->value => [
            'find_origin_threshold' => TimeHelper::millisecond(100),
        ],
    ]
),
```

Now, only queries that take longer than 100 milliseconds will include the file and line number where the query was executed. By default, queries slower than 300 milliseconds will be collected.

Be careful not to set this threshold too low, as it can cause a lot of overhead in your application.

## Bindings

When you're passing bindings alongside your query, Flare will automatically collect them.

It is possible to disable sending query bindings as such:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Queries->value => [
            'include_bindings' => false,
        ],
    ]
),
```

## Manually recording queries

If you're executing queries outside of Laravel's database layer, you can record them manually. The [PHP documentation](/docs/php/data-collection/queries) provides a full overview of all available recorder methods. When using these methods in Laravel, use the `Flare` facade instead of `$flare`:

```php
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\FlareClient\Time\TimeHelper;

Flare::query()->record(
    query: 'SELECT * FROM users WHERE id = ?',
    duration: TimeHelper::milliseconds(300),
    bindings: [1],
    databaseName: 'mysql',
    driverName: 'mysql',
);
``` 
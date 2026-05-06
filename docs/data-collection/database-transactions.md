---
title: Database transactions 
---


Flare can collect information about the database transactions being executed in your application. This includes:

- Whether the transaction was committed or rolled back

This functionality is enabled by default, but you can disable it by ignoring the `Transactions` collect in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::Transactions],
),
```

Transaction collection will happen automatically.

## Manually recording transactions

If you're working with database connections that aren't managed by Laravel, you can record transactions manually. The [PHP documentation](/docs/php/data-collection/database-transactions) provides a full overview of all available recorder methods. When using these methods in Laravel, use the `Flare` facade instead of `$flare`:

```php
use Spatie\LaravelFlare\Facades\Flare;

Flare::transaction()->recordBegin();

// ... perform the transaction

Flare::transaction()->recordCommit();
``` 
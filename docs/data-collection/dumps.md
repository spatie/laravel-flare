---
title: Dumps 
---


Flare collects information about the dumps that are being executed in your application.

You can disable this behaviour by ignoring the `Dumps` collect in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::Dumps],
),
```

You can configure the maximum number of dumps tracked while collecting data in the case of an error as such:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Dumps->value => [
            'max_items_with_errors' => 10,
        ],
    ]
),
``` 
---
title: Views 
---


Flare can collect information about the views being rendered in your application. This includes:

- The view name
- The view path
- The view loop (if running within a loop)

We do not collect other view data by default because payloads can be large.

This functionality is enabled by default, but you can disable it by ignoring the `Views` collect in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::Views],
),
```

You can configure the maximum number of views tracked while collecting data in the case of an error as such:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Views->value => [
            'max_items_with_errors' => 10,
        ],
    ]
),
```

## Manually recording views

If you're rendering views through a templating engine other than Blade, you can record them manually. The [PHP documentation](/docs/php/data-collection/views) provides a full overview of all available recorder methods. When using these methods in Laravel, use the `Flare` facade instead of `$flare`:

```php
use Spatie\LaravelFlare\Facades\Flare;

Flare::view()->recordRendering(
    name: 'my-view',
    data: ['name' => 'Spatie'],
    file: '/path/to/view.php',
);

// ... render the view

Flare::view()->recordRendered();
``` 
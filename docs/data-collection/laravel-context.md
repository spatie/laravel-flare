---
title: Laravel context 
---


Flare can collect [Laravel Context](https://laravel.com/docs/12.x/context) when an exception is thrown within your application.

It is possible to disable the collection of context information by ignoring the `LaravelContext` collect in `config.php`:

```php
use Spatie\LaravelFlare\Enums\LaravelCollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::LaravelContext],
),
``` 

---
title: Errors when tracing 
---


When an error occurs in your application, Flare will receive a full error report with a stack trace and extra context.

When you're tracing, Flare will also automatically track the errors and store them as events on the current span.

It is possible to disable this behaviour by ignoring the `ErrorsWithTraces` collect in the `flare.php` config file:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::ErrorsWithTraces],
),
``` 
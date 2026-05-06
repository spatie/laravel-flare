---
title: Exception context 
---


Flare can collect extra context information added to an exception as such:

```php
use Exception;

class ExceptionWithContext extends Exception
{
    public function context(): array
    {
        return [
            'key' => 'value',
        ];
    }
}
```

The context will be collected automatically when the exception is thrown.

It is possible to disable the collection of context information by ignoring the `ExceptionContext` collect in `config.php`:

```php
use Spatie\LaravelFlare\Enums\LaravelCollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::ExceptionContext],
),
``` 
---
title: Application info 
---


By default, Flare is adding information about your current running application:

- The locale
- If the config has been cached
- Whether the debug mode is on
- The version of the Laravel framework
- The current environment in which your application is running
- The application name

This behaviour can be disabled by ignoring the `LaravelInfo` collect in `config.php`:

```php
use Spatie\LaravelFlare\Enums\LaravelCollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [LaravelCollectType::LaravelInfo],
),
```

Please note that the application name and stage will always be sent to Flare.

## Application Version

You can configure Flare to add a version number to all sent exceptions and traces:

```php
use Spatie\LaravelFlare\Facades\Flare;

Flare::withApplicationVersion("1.0");
```

An excellent place to put this code is within your `AppServiceProvider` boot method.

It is also possible to use a closure to set the version number:

```php
Flare::withApplicationVersion(function() {
   return '1.0' ; // return your version number
});
``` 
---
title: Requests 
---


Flare can collect information about the requests being made to your application. This includes:

- The request method
- The request URL
- The body size & contents
- The user agent
- The IP address of the user
- The request headers
- The request cookies
- The request query parameters
- The request files
- The request session data
- The request route
- The request route parameters
- Livewire components
- The authenticated user

This functionality is enabled by default, but you can disable it by ignoring the `Request` collect in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::Requests],
),
```

It is possible to filter out fields from the request body, headers, and the user's IP address. You can read more about this [here](/docs/laravel/general/censoring-collected-data).

By default, livewire components passed to the request are also collected. This can be disabled by ignoring the `Livewire` collect in `config.php`:

```php
use Spatie\LaravelFlare\Enums\LaravelCollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [LaravelCollectType::LivewireComponents],
),
```

## Grouping unmatched route errors

When a request hits a URL that doesn't match any registered route (for example, bots scanning for `wp-admin.php`), Flare will automatically set the `http.route` attribute to `errors::{status_code}` (e.g. `errors::404`). This groups all unmatched 4xx requests under a single route label instead of cluttering your dashboard with individual URLs.

This behavior is enabled by default. You can disable it in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Requests->value => [
            'group_unmatched_route_errors' => false,
        ],
    ],
),
```

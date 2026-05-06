---
title: Adding custom context 
---


When you send an error to Flare, we already collect a lot of Laravel and user-specific information for you and send it along with the errors that happened in your application.
You can also add custom context to your application. This can be very useful if you want to provide key-value-related information that helps you debug a possible error.

For example, your application could be in a multi-tenant environment. In addition to reporting the user, you want to provide a key that quickly lets you identify which tenant was active when the error occurred.

Flare allows you to set custom context items using the following:

```php
use Spatie\LaravelFlare\Facades\Flare;

Flare::context('tenant', 'My-Tenant-Identifier');
```

This could be set automatically in a Laravel service provider or an event. The next time an exception occurs, this value will be sent along to Flare, and you can find it on the "Context" tab.

It is also possible to send multiple context items at once:

```php
use Spatie\LaravelFlare\Facades\Flare;

Flare::context([
    'tenant_id' => 'My-Tenant-Identifier',
    'tenant_name' => 'My-Tenant-Name'
]); 
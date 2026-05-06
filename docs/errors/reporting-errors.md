---
title: Reporting errors 
---


PHP defines two types of "things that might go wrong within your application":

- Exceptions
- Errors

While the first is the most common, the second is also a valid way of handling errors in PHP. A fatal error is an example of a PHP error that cannot be caught by a try-catch block.

The Flare client can handle exceptions and errors; it wraps errors within `ErrorException` instances and sends them to Flare.

It is possible to set the minimum error level that will be sent to Flare. By default, all errors are sent to Flare. You can change this by calling `report_error_levels` in the `flare.php` config file:

```php
'report_error_levels' => E_ALL & ~E_NOTICE, // Will send all errors except E_NOTICE errors
```

Setting the `error_levels` config value to `null` will send all errors to Flare:

```php
'report_error_levels' => null,
``` 
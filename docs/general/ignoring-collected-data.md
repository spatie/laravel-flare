---
title: Ignoring collected data 
---


## Ignoring exceptions

By default, all exceptions will be reported to Flare. You can change this behaviour by filtering the exceptions with a callable:

```php
use \Spatie\LaravelFlare\Facades\Flare;

Flare::filterExceptionsUsing(
    fn(Throwable $throwable) =>  !$throwable instanceof AuthorizationException
);
```

## Ignoring reports

Additionally, you can provide a callable to the `Flare::filterReportsUsing` method to stop a report from being sent to Flare. Compared to `filterExceptionsCallable`, this can also prevent logs and errors from being sent.

```php
Flare::filterReportsUsing(function(Report $report)  {
    // return a boolean to control whether the report should be sent to Flare
    return true;
});
```

## Ignoring errors

Finally, it is also possible to set the levels of errors reported to Flare as such:

```php
Flare::reportErrorLevels(E_ALL & ~E_NOTICE); // Will send all errors except E_NOTICE errors
```

## Ignoring spans

At the moment, it is not possible to filter out spans since they depend on each other. Removing spans would break the inheritance required for performance monitoring.

## Ignoring Flare data collection

Flare collects a lot of data by default. We define many types of collects (queries, requests, etc.) that are sent to Flare. You can ignore these collects in the `collects` key in your `flare.php` config file. 
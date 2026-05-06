---
title: Customise the error report 
---


Before Flare receives the data collected from your local exception, we allow you to call custom middleware methods.

These methods retrieve the report factory that will eventually be sent to Flare and allow you to add custom information to that report.

You can create a Flare middleware as such:

```php
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;

class MyMiddleware implements FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next): ReportFactory;
    {
        $report->handled(true);

        return $next($report);
    }
}
```

You need to register the middleware as follows in the `flare.php` config file:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::FlareMiddleware->value => [
            'middleware' => [
                MyMiddleware::class => [],
            ],
        ],
    ]
),
```

You can also pass additional options to the middleware as such:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::FlareMiddleware->value => [
            'middleware' => [
                MyMiddleware::class => ['mark_every_error_handled' => false],
            ],
        ],
    ]
),
```

The middleware will get a `$config` array injected within its constructor. You can use this to get the options that were passed to the middleware:

```php
class MyMiddleware implements FlareMiddleware
{
    protected bool $markEveryErrorHandled = false;

    public function __construct(
        array $config
    ) {
        $this->markEveryErrorHandled = $config['mark_every_error_handled'] ?? false;
    }

    public function handle(ReportFactory $report, Closure $next): ReportFactory;
    {
        $report->handled($this->config['mark_every_error_handled']);

        return $next($report);
    }
}
``` 
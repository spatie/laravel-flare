---
title: Jobs & queues 
---


Flare can collect information about the jobs being executed in your application. This includes:

- The job name
- The job class
- The job queue
- The job connection
- The job UUID
- The job tags
- The job properties
- And so much more...

This functionality is enabled by default, but you can disable it by ignoring the `Jobs` collect in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::Jobs],
),
```

You can configure the maximum number of jobs tracked while collecting data in the case of an error as such:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Jobs->value => [
            'max_items_with_errors' => 10,
        ],
    ]
),
```

When a job is executed, its further chain of jobs will be collected to provide even more insight into the job and its chain. It is possible to define the maximum depth of such a chain as such:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Jobs->value => [
            'max_chained_job_reporting_depth' => 5,
        ],
    ]
),
```

To disable the inspection of job chains, you can set the depth to `0`:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Jobs->value => [
            'max_chained_job_reporting_depth' => 0,
        ],
    ]
),
```

When you want to ignore specific job classes, you can do so by adding them to the `ignore` array:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Jobs->value => [
            'ignore' => [
                App\Jobs\SomeJob::class,
                App\Jobs\AnotherJob::class,
            ],
        ],
    ]
),
```

## Laravel Horizon

It is possible to connect a Flare project with your Laravel Horizon installation. This way, you can quickly jump from an
exception to the corresponding job in Horizon and retry it.

### Configuration

Within your project settings, open up the Laravel page:

![screenshot](/images/docs/laravel-horizon-1.png)

Provide the URL to your Laravel Horizon dashboard(most of the time, this looks
like `https://your-app-domain.com/horizon`) and click save.

### Usage

When viewing an exception in Flare triggered by a Horizon Job, you can now quickly jump to the corresponding job in Horizon:

![screenshot](/images/docs/laravel-horizon-2.png)

Please note that by default, Laravel Horizon will keep the failed job information for seven days; failed jobs older than seven days will no longer be available in Horizon. You can adjust this setting in your `horizon.php` configuration by updating the `trim` option:

```php
'trim' => [
    'recent' => 60,
    'pending' => 60,
    'completed' => 60,
    'recent_failed' => 43200, // 30 days
    'failed' => 43200, // 30 days
    'monitored' => 10080,
],
``` 

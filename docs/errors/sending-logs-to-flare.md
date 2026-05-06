---
title: Sending logs to Flare 
---


In addition to handling your application errors, you may also want to send specific error messages to Flare.

These error messages are not necessarily errors but log statements that exceed a specified threshold—think of critical logs that your application sends and that you want to be notified about.

## Activating/Deactivating log reporting

In your flare.php config file, you can enable or disable log reporting. The `send_logs_as_events` key determines whether Flare should receive your error logs automatically.

In order to feed the logs from Laravel to Flare add following configuration to your `config/logging.php` file:

```php
'channels' => [

    // other channels...

    'flare' => [
        'driver' => 'flare',
    ],
],
```

And then within your `.env` file add the Flare channel to the `LOG_STACK` variable:

```bash
LOG_STACK=single,flare
```

Please note, in older Laravel versions the `LOG_STACK` variable is not available. In that case, you can add the Flare logger directly to the `config/logging.php` file under the `stack` channel:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'flare'],
    ],
    
        'flare' => [
        'driver' => 'flare',
    ],

    // other channels...
],
```

When you've configured another default logging channel other than stack, please update your stack accordingly with the Flare channel.

## Controlling the minimum log level to report

By default, we only collect logs that are at least of the type "Error" or above (Emergency, Critical). That means we do not receive simple info logs you perform in your application.

You can modify the minimum report log level by specifying it by adding or modifying the `level` key in your logging channel configuration.

For example, this would restrict logs to being sent to Flare only when they are at the "critical" level.

```php
// in your config/logging.php
'flare' => [
    'driver' => 'flare',
    'level' => 'critical',
],
```

This would mean that the following log calls would be sent to Flare:

```php
Log::critical('Something went wrong');
Log::alert('Something went wrong');
Log::emergency('Something went wrong');
```

But these log calls would not be sent to Flare:

```php
Log::debug('Something went wrong');
Log::info('Something went wrong');
Log::notice('Something went wrong');
Log::warning('Something went wrong');
Log::error('Something went wrong');
``` 

## Sending stack traces with your logs

By default, we do not send stack traces alongside your log messages, this because it requires us to do a backtrace on every log call, which can be quite expensive.

If you want to enable sending stack traces, you can do so by adding the `stack_traces` key to your logging channel configuration:

```php
'flare' => [
    'driver' => 'flare',
    'stack_trace' => true,
],
```

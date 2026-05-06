---
title: Console commands 
---


Flare can collect information about the console commands that are being executed. Whether an error happens during a command or you want to trace a long-running command, Flare will collect the following information:

- The command name
- The command arguments
- The exit code

This functionality is enabled by default, but you can disable it by ignoring the `Commands` collect in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::Commands],
),
```

## Manually recording commands

If you're running commands or subprocesses that aren't automatically tracked by Laravel, you can record them manually. The [PHP documentation](/docs/php/data-collection/console-commands) provides a full overview of all available recorder methods. When using these methods in Laravel, use the `Flare` facade instead of `$flare`:

```php
use Spatie\LaravelFlare\Facades\Flare;

Flare::command()->recordStart('my:command', ['--option' => 'value']);

// ... run the command

Flare::command()->recordEnd(exitCode: 0);
``` 
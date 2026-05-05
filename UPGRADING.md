# Upgrading

There are some breaking changes you should be aware of. We've categorized them so you can prioritize. This guide covers the most common cases. Edge cases may not be covered, and PRs to improve it are welcome.

## From v2 to v3

Most applications upgrade by editing `config/flare.php` and adding a `flare` log channel. The deeper changes (recorder subclasses, custom senders, custom samplers, custom attribute providers) only matter if you previously extended Flare's internals.

### What's new in v3

A few new concepts are referenced throughout this guide.

* **First class logging.** A dedicated logger sends log entries to Flare in the OpenTelemetry log format. You opt in by enabling `log` in `config/flare.php` and adding a `flare` log channel.
* **Dynamic sampling.** A new `DynamicSampler` selects a sample rate per entry point (route, command, job) using `SamplingRule` definitions, without requiring a custom sampler.
* **Lifecycle.** A new `Lifecycle` class manages the application start, subtask boundaries (Octane, queue workers), termination, flushing, and reset behavior. The previous `TracingKernel`, `Flare::reset()`, and `Flare::sendReportsImmediately()` are gone.
* **Entry points.** A new `EntryPoint` value object resolved by `EntryPointResolver` describes the request, command, or job that initiated a trace. It replaces the loose `entryPointClass` arguments and the array context previously passed to samplers, and feeds the new `flare.entry_point.handler.*` attributes.
* **Attribute providers.** Recorders now accept dedicated provider contracts (`RequestAttributesProvider`, `ResponseAttributesProvider`, `RouteAttributesProvider`, `CommandAttributesProvider`, `JobAttributesProvider`, `UserAttributesProvider`) instead of loose arguments. Most users do not interact with these directly. Custom subclasses do.
* **Reorganized recorders.** `RoutingRecorder`, `CommandRecorder`, and `JobRecorder` always boot for entry point detection, regardless of `collects` config. Span creation stays guarded by sampling.

### Update your config file

The shape of `config/flare.php` changed. Either republish the config file from the package, or apply the diff manually.

```bash
php artisan vendor:publish --tag=flare-config --force
```

Notable keys.

* `send_logs_as_events` was removed. Log shipping is now controlled by the `log` key (see [Logging setup](#logging-setup) below).
* `attribute_providers` was removed. The user, console, and request providers are wired up automatically. 
* `trace` defaults to `true` (previously `false`). Set it explicitly if you don't want tracing on by default.
* `log` was added to enable or disable the new log shipping feature. Defaults to `false`.
* `minimal_log_level` was added (Monolog `Level` instance, or `null` to send every level).
* For job collection, the ignore list moved from `ignore` to `ignored_classes`.


### Logging setup

Flare now has a dedicated log shipping pipeline. To use it.

1. Install the [Flare Daemon](https://github.com/spatie/flare-daemon) (this is not required but recommended).

2. Enable logging and switch the sender to the daemon in `config/flare.php`.

    ```php
    'log' => true,

   // Only needed if using the daemon. 
    'sender' => [
        'class' => \Spatie\FlareClient\Senders\DaemonSender::class,
    ],
    ```

3. Add a `flare` log channel in `config/logging.php`.

    ```php
    'channels' => [
        'flare' => [
            'driver' => 'flare',
        ],
    ],
    ```

4. Include it in the active stack via your `.env`.

    ```
    LOG_STACK=single,flare
    ```

If you keep the defaults, the daemon sender talks to `http://127.0.0.1:8787` and uses its built-in timeout and fallback behavior. Test payloads (sent via `php artisan flare:test`) talk directly to the daemon and do not fall back.

The Flare daemon must be running alongside your application. Refer to the Flare documentation for deployment patterns (process manager, supervisor, systemd, or Octane sidecar).

### `MessageLevels` enum replaced by Monolog's `Level`

Anywhere you used `Spatie\FlareClient\Enums\MessageLevels`, switch to `Monolog\Level`.

```php
// Before
$flare->glow()->record('Hello', MessageLevels::Debug);

// After
$flare->glow()->record('Hello', \Monolog\Level::Debug);
```

### Other changes

If you've never manually called Flare, or configured anything outside the config file, you can skip the rest of this guide. If you have, read on for the details.

#### `Flare::reset()`, `Flare::sendReportsImmediately()`, and `Flare::application()` were removed

All three were removed in favor of `Lifecycle`. Drop direct calls.

```php
// Before
Flare::reset();
Flare::sendReportsImmediately();
Flare::application()->recordTerminating();

// After
// Nothing. Lifecycle handles boot, register, terminate, queue boundaries, and Octane resets.
```

#### Custom recorder subclasses

If you extended any recorder, the constructor signatures and several `record*` methods changed.

* `RoutingRecorder::recordRoutingEnd()` accepts a `RouteAttributesProvider` instead of a route string.
* `CommandRecorder::recordStart()` accepts a `CommandAttributesProvider` (typically `LaravelCommandAttributesProvider`). Local ignore logic moved to `defaultIgnoredCommands()`.
* `JobRecorder::recordStart()` accepts a `JobAttributesProvider` and an optional `traceparent`. Lifecycle subtask handling lives in the base. Override `defaultIgnoredJobClasses()` for the ignore list.
* `RequestRecorder::recordStart()` accepts a `RequestAttributesProvider`. `recordEnd()` accepts request, response, route, and user providers, plus optional attributes.
* `Lifecycle::start()` and `Lifecycle::startSubtask()` no longer accept a `samplerContext` array. The `EntryPointResolver` is the single source of truth.
* All recorders that contribute to entry point detection (`RoutingRecorder`, `CommandRecorder`, `JobRecorder`) take an `EntryPointResolver` in their constructor.

#### Custom attribute providers

The classes under `Spatie\LaravelFlare\AttributesProviders` were rewritten around dedicated contracts.

* `LaravelUserAttributesProvider` now takes the user object in the constructor. The `id()`, `fullName()`, `email()`, and `attributes()` methods are parameterless. Use `LaravelUserAttributesProvider::fromRequest($request)` for the typical case.
* `LaravelRequestAttributesProvider` now takes `Redactor`, `LivewireComponentFinder`, the request, and content/Livewire flags in its constructor. `toArray()` is parameterless.
* `LaravelJobAttributesProvider` now takes the job in its constructor and exposes `jobName()`, `jobClass()`, and the new `EntryPointHandlerProvider` methods.
* New: `LaravelCommandAttributesProvider`, `LaravelRouteAttributesProvider`, `LaravelQueuedJobAttributesProvider`, plus the `Concerns\ResolvesJobPayloadAttributes` trait.

If you wired your own provider through the old `attribute_providers` config, point the relevant recorder at your subclass instead. Most subclasses can extend the new Laravel providers and override only the method they need.

#### Custom senders

The `Sender` interface gained a `bool $test` parameter and renames `FlarePayloadType` to `FlareEntityType`. If you ship a custom sender:

```php
// Before
public function post(string $endpoint, string $apiToken, array $payload, FlarePayloadType $type, Closure $callback): void;

// After
public function post(string $endpoint, string $apiKey, array $payload, FlareEntityType $type, bool $test, Closure $callback): void;
```

`LaravelHttpSender` and `LaravelVaporSender` were updated accordingly. The Vapor sender also gained a `queue_logs` config option and no longer queues test payloads.

#### The base Flare client package

The base `spatie/flare-client-php` package was rewritten. Most laravel-flare users do not touch it directly, but if you do (custom samplers, senders, or recorders, vanilla PHP integrations), read its [upgrade guide](https://github.com/spatie/flare-client-php/blob/main/UPGRADING.md) as well.

## From v1 to v2

The second version of the package has been a complete rewrite, we've added some interesting points in this upgrade guide but advise you to read the docs again.

- The package now requires PHP 8.2 or higher and Laravel 11.0 or higher.
- Start with removing the `flare.php` and replace it with the new `flare.php` file. The  config file which you can find [here](https://github.com/spatie/laravel-flare/blob/main/config/flare.php).
- In the previous version when anonymising user Ip's a middleware had to be removed, in this version you can set the `censor.client_ips` option to `true` in the config file.
- The `CensorRequestBodyFields` middleware was removed. You can now use the `censor.body_fields` option in the config file to specify which fields should be censored.
- The `CensorRequestHeaders` middleware was removed. You can now use the `censor.headers` option in the config file to specify which headers should be censored.
- In the previous version Flare would send a whole copy of the user model when logged in. In this version only an id, email, name and some attributes (if the `toFlare` method is implemented on the user) of the user will be sent. This can be configured by creating your own `UserAttributesProvider`
- A lot of middlewares and recorder have been rewritten or deleted, if you were extending from these please check the new ones.
- In the past when you wanted to disable the collecting of specific data you had to remove the middleware. In this version you can set the `ignore` option in the config file of certain `collects` you want to disable.
- The method `reportErrorLevels` on the Flare facade has been removed in favor of the `report_error_levels` config option.
- The `$flare::context()` method works a bit different now, the concept of groups has been removed. A single context item still can be added like this:

```php
$flare::context('key', 'value'); // Single item
```

Multiple context items can be added like this:

```php
$flare::context([
    'key' => 'value',
    'key2' => 'value2',
]);
```
- The `group` method to add context data has been removed, you should just use the `context()` method
- We've changed how glows are added (MessageLevels is now an enum and slightly renamed):

```php
$flare::glow('This is a message from glow!', MessageLevels::DEBUG); // Old way

$flare::glow()->record('This is a message from glow!', MessageLevels::Debug); // New way
```
- The `determineVersion` method was renamed to `withApplicationVersion`
- Stackframe arguments are now collected by default. You can disable this by ignoring the `CollectType::StackFrameArguments` in the config file.
- Setting the argument reducers for stack frame arguments has changed, take a look at the docs for more info.
- Adding custom middleware is still possible but the way to do this has changed, take a look at the docs for more info.

## From spatie/laravel-ignition

We created `spatie/laravel-flare` to make it easier to use Flare in Laravel without adding Ignition our custom build
error page. This package can only be installed on Laravel 11.10 and up.

When upgrading, please merge the contents of the `ignition.php` config file into the `flare.php` config file. And check
if new config options are available in the `flare.php` config file.

Don't forget to remove the `spatie/laravel-ingition` dependency since it cannot work together with `spatie/laravel-flare`.

That's it!

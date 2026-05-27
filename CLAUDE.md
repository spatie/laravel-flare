# CLAUDE.md

Guidance for working in this repository.

## Architecture

`spatie/laravel-flare` is the Laravel adapter for `spatie/flare-client-php`. The client package owns all framework-agnostic behaviour (reporting, tracing, recorders, senders, the HTTP API, the lifecycle state machine). This package adds the Laravel-specific glue.

### Package boundary

Belongs in `spatie/flare-client-php`:
- Anything that can run without Laravel installed.
- Core abstractions: `Flare`, `Tracer`, `FlareProvider`, the base `FlareConfig`, `Lifecycle`, `Report`, `Span`, plus recorders/middleware/senders that only need PHP.
- The `Tester` base class.

Belongs in `spatie/laravel-flare`:
- Anything that touches `Illuminate\*`, facades, Eloquent, Blade, Livewire, Octane, or Vapor.
- Laravel subclasses of client base classes (e.g. `LaravelStacktraceMapper`, `LaravelTester`, `LaravelHttpSender`, recorders under `src/Recorders/` that consume Laravel events).
- The service provider that wires everything into the container.

When adding behaviour, default to extending an existing client class on the Laravel side rather than spinning up a new value object. If a feature is useful outside Laravel, push it down into the client package and subclass here.

### Bootstrapping (`FlareServiceProvider`)

`register()`:
1. Merges `config/flare.php` and hydrates a `FlareConfig` via `FlareConfig::fromLaravelConfig()`. The Laravel `FlareConfig` extends the client's `FlareConfig` and is aliased to the base class in the container.
2. Constructs a `FlareProvider` (from the client package) with the config, the container, and a handful of Laravel-specific closures (recorder registration callback, subtask detection, graceful span ender).
3. Registers Laravel-only singletons (`BackTracer`, `LaravelStacktraceMapper`, view frame mappers, Livewire support).
4. Extends `Resource` and `Scope` with Laravel telemetry attributes.
5. Calls `$provider->register()`, which binds all configured recorders and Flare middleware.

`boot()`:
1. Publishes the config stub and registers `TestCommand`.
2. Calls `$provider->boot()`.
3. Wires Octane event listeners for subtask lifecycle.
4. Maps `ViewException` through the framework exception handler.
5. Drives the client `Lifecycle` state machine (`start`, `register`, `registered`, `boot`, `booted`, `terminating`, `terminated`) using Laravel's container hooks.
6. If tracing is enabled, extends the route dispatchers and prepends `FlareTracingMiddleware`.

### Config hydration

`FlareConfig::fromLaravelConfig()` reads `config/flare.php` into a typed `FlareConfig`. Two rules apply:

1. Helpers called from a config file must never throw. Return `null` on bad input and filter at the call site. Config files load extremely early; an exception there breaks the whole app.
2. Unknown `collects` types are silently skipped via `tryFrom`. This is deliberate so configs can reference recorders that may not exist in older versions.

### Subsystems (`src/`)

- `FlareMiddleware/`: per-report enrichers (request info, console info, view info, exception context, handled status). Runs synchronously when a report is built.
- `Recorders/`: long-lived collectors for cache, queries, jobs, queues, HTTP, filesystem, Livewire, notifications, routing, transactions, views, etc. Subscribe to Laravel events and feed spans/breadcrumbs.
- `Sampling/`: `SamplingRule` subclasses (route name, route action, queue name, queue connection) consumed by the client's sampler.
- `Senders/`: Laravel-specific transports (`LaravelHttpSender`, `LaravelVaporSender`).
- `ArgumentReducers/`: turn Laravel-specific argument types (Eloquent models, collections, views) into reportable representations.
- `AttributesProviders/`: produce OpenTelemetry attributes for requests, routes, jobs, commands, users, Livewire components.
- `Http/`: `FlareTracingMiddleware` plus route dispatcher wrappers that open spans around controller/closure dispatch.
- `Commands/`: `TestCommand` (`flare:test`).
- `Jobs/SendFlarePayload.php`: queued job for async sending.
- `Views/`: Blade source map compiler, view exception mapper, view/Livewire frame mappers. Used to translate compiled-Blade stack frames back to source.
- `Support/`: small Laravel-aware utilities (`BackTracer`, `CollectsResolver`, `FlareLogHandler`, `Telemetry`, `LivewireComponentFinder`, `LaravelStacktraceMapper`).
- `Enums/`, `Exceptions/`, `Facades/`, `Filesystem/`: small support types.

The client package mirrors much of this layout under `vendor/spatie/flare-client-php/src/` and is the source of truth for shared abstractions.

## Running the test suite

The suite includes integration tests that hit a running workbench HTTP server and queue worker. Without those running, ~64 integration tests will fail. The full sequence:

```bash
composer install
composer run build                  # builds the workbench (migrations, storage symlink, etc.)
vendor/bin/testbench serve &        # http server
vendor/bin/testbench queue:work &   # queue worker
vendor/bin/pest                     # run all tests
```

After the suite finishes, kill the background processes:

```bash
pkill -f "testbench serve"
pkill -f "testbench queue:work"
```

For unit tests only (no workbench needed):

```bash
vendor/bin/pest --exclude-group=integration
```

## Testing against a local flare-client-php checkout

When changes to `spatie/flare-client-php` need verification against this package, symlink the local checkout via a path repository instead of editing `vendor/` by hand.

Add the following to `composer.json` (next to `config`):

```json
"repositories": [
    {
        "type": "path",
        "url": "../flare-client-php",
        "options": { "symlink": true }
    }
],
"minimum-stability": "dev",
"prefer-stable": true
```

Then:

```bash
composer update spatie/flare-client-php --with-all-dependencies
```

Verify the symlink with `ls -la vendor/spatie/flare-client-php` (should show `->  ../../../flare-client-php/`).

Run the full integration suite as above to catch regressions.

To reset, back up `composer.json` and `composer.lock` before linking, then restore them and run `composer install` after testing.

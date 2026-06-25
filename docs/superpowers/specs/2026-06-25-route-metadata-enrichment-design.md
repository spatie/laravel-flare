# Route metadata enrichment

## Background

Laravel 13 adds first-class route metadata ([laravel/framework#60530](https://github.com/laravel/framework/pull/60530)). Routes can declare arbitrary structured data:

```php
Route::get('/users', [UserController::class, 'index'])
    ->metadata(['head' => ['title' => 'Users']]);
```

and read it back with optional dot notation:

```php
$request->route()->getMetadata('head.title'); // 'Users'
$request->route()->getMetadata();             // the full metadata array
```

Metadata is stored under the route action's `metadata` key, cascades through route groups, and survives `route:cache`. The setter (`metadata()`, `setMetadata()`) and reader (`getMetadata()`) only exist in Laravel 13+. This package targets Laravel 11, 12, and 13 (`illuminate/support: ^11.47|^12.42|^13.0`), so on 11/12 a route can never carry metadata and the API is absent.

## Goal

When a route declares metadata, surface it on Flare traces and error reports as a single route attribute. Enrichment only: Flare passively reports whatever the developer attached. No configuration, no behaviour driven by metadata (no per-route sampling, ignoring, or span renaming).

## Scope

In scope:

- Add `laravel.route.metadata` to the route attributes that Flare already collects, so it appears wherever the other `laravel.route.*` attributes appear (the request/routing span when tracing is enabled, and error reports).

Out of scope (explicitly):

- Flattening metadata into per-key attributes (e.g. `laravel.route.metadata.head.title`). The value is attached as-is.
- A reserved `flare` metadata key or any behaviour driven by metadata (sampling, ignore, span naming).
- A config flag to toggle collection.
- Changes to sampling, recorders, middleware, or the `spatie/flare-client-php` package.

## Design

### Single integration point

All work happens in `src/AttributesProviders/LaravelRouteAttributesProvider.php`. This is the one class that maps a `Route` into the `laravel.route.*` attributes consumed by both the trace span and error reports, so adding one attribute here makes it appear in both places with no other changes.

The other route-facing classes (`RoutingRecorder`, `FlareTracingMiddleware`, the sampling rules, the client package) are untouched.

### Behaviour

`toArray()` currently returns a flat array literal where every key is emitted unconditionally (`laravel.route.name` is sent even as `null`; `laravel.route.parameters` and `laravel.route.middleware` are sent as `[]` when empty). Metadata follows the same "always emit on supported versions" convention, with one version-driven exception.

- Build the existing base array, then conditionally add `laravel.route.metadata`.
- Add the key only when the framework supports route metadata, detected with `method_exists($this->route, 'getMetadata')`:
  - **Laravel 13+**: always add `laravel.route.metadata` => `$this->route->getMetadata()`, even when the metadata array is empty (`[]`). This matches how `parameters` and `middleware` are always emitted.
  - **Laravel 11/12**: omit the key entirely. The attribute reflects a framework capability that does not exist on those versions, so emitting it (even as `[]`) would misleadingly imply support.
- The value is the full metadata array attached as-is, preserving nested arrays. This is consistent with how `laravel.route.parameters` and `laravel.route.middleware` already store array values.

A small private helper encapsulates the read so `toArray()` stays readable, for example:

```php
public function toArray(): array
{
    $attributes = [
        'http.route' => $this->route->uri(),
        'laravel.route.name' => $this->route->getName(),
        'laravel.route.parameters' => $this->getRouteParameters($this->route),
        'laravel.route.middleware' => array_values($this->route->gatherMiddleware()),
        'laravel.route.action' => $this->resolvedAction['name'],
        'laravel.route.action_type' => $this->resolvedAction['type'],
    ];

    if (method_exists($this->route, 'getMetadata')) {
        $attributes['laravel.route.metadata'] = $this->route->getMetadata();
    }

    return $attributes;
}
```

Reading through the public `getMetadata()` method (rather than `$route->getAction('metadata')`) keeps Flare on the documented API instead of the action-array storage detail.

`samplingAttributes()` is not changed: metadata does not drive sampling.

## Testing

Extend the existing `LaravelRouteAttributesProvider` coverage:

- A route declaring metadata produces `laravel.route.metadata` holding the expected nested array.
- A route declaring no metadata still produces `laravel.route.metadata` equal to `[]` (the always-emit case on supported versions).
- Both assertions are skipped when the framework lacks `getMetadata` (Laravel < 13) so the suite stays green across all supported versions. Guard with the same `method_exists` check, or skip when `! method_exists(Route::class, 'getMetadata')`.

No integration-test changes are required; this rides the existing route-attribute flow.

## Compatibility

- Laravel 11/12: no behaviour change. The key is never added.
- Laravel 13+: one new attribute on route spans and reports.
- No changes to the `spatie/flare-client-php` package.

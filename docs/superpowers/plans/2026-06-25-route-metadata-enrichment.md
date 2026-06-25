# Route Metadata Enrichment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Surface Laravel 13 route metadata on Flare traces and error reports as a single `laravel.route.metadata` attribute.

**Architecture:** Add one attribute to `LaravelRouteAttributesProvider::toArray()`, the single class that maps a `Route` into the `laravel.route.*` attributes consumed by both trace spans and error reports. The attribute is added only when the framework supports route metadata.

**Tech Stack:** PHP 8.2+, Laravel 11/12/13, Pest.

Spec: `docs/superpowers/specs/2026-06-25-route-metadata-enrichment-design.md`

## Global Constraints

- Package targets Laravel 11, 12, and 13 (`illuminate/support: ^11.47|^12.42|^13.0`). Code must run on all three.
- The route metadata API (`getMetadata()`) exists only in Laravel 13+. Detect with `method_exists($route, 'getMetadata')`.
- Enrichment only: no flattening, no config flag, no sampling/behaviour changes, no changes to `spatie/flare-client-php`.
- Follow the existing `toArray()` convention: on supported versions the key is always emitted, even when the value is empty.
- Read through the public `getMetadata()` method, not the `metadata` action-array key.

---

### Task 1: Add `laravel.route.metadata` to route attributes

**Files:**
- Modify: `src/AttributesProviders/LaravelRouteAttributesProvider.php:41-51` (the `toArray()` method)
- Test: `tests/AttributesProviders/LaravelRouteAttributesProviderTest.php`

**Interfaces:**
- Consumes: `Illuminate\Routing\Route::getMetadata(?string $key = null, mixed $default = null): mixed` (Laravel 13+ only; returns the full metadata array when called with no key).
- Produces: `LaravelRouteAttributesProvider::toArray(): array` now includes key `laravel.route.metadata` => `array` when running on Laravel 13+; the key is absent on Laravel 11/12.

- [ ] **Step 1: Write the failing tests**

Append to `tests/AttributesProviders/LaravelRouteAttributesProviderTest.php`. The two tests are skipped on Laravel < 13 where `Route::metadata()` / `Route::getMetadata()` do not exist.

```php
it('returns the route metadata', function () {
    $route = Route::get('/route/', fn () => null)
        ->metadata(['head' => ['title' => 'Users']]);

    $request = Request::create('/route', 'GET');
    $route->bind($request);

    $attributes = (new LaravelRouteAttributesProvider($route, $request->getMethod()))->toArray();

    expect($attributes['laravel.route.metadata'])->toBe(['head' => ['title' => 'Users']]);
})->skip(
    fn () => ! method_exists(\Illuminate\Routing\Route::class, 'getMetadata'),
    'Route metadata requires Laravel 13+',
);

it('returns an empty array when a route has no metadata', function () {
    $route = Route::get('/route/', fn () => null);

    $request = Request::create('/route', 'GET');
    $route->bind($request);

    $attributes = (new LaravelRouteAttributesProvider($route, $request->getMethod()))->toArray();

    expect($attributes['laravel.route.metadata'])->toBe([]);
})->skip(
    fn () => ! method_exists(\Illuminate\Routing\Route::class, 'getMetadata'),
    'Route metadata requires Laravel 13+',
);
```

- [ ] **Step 2: Run the tests to verify they fail (or skip on Laravel < 13)**

Run: `vendor/bin/pest --filter="route metadata"`

Expected on Laravel 13+: FAIL with an undefined-index / key-not-found error on `laravel.route.metadata`.
Expected on Laravel 11/12: both tests reported as SKIPPED (this is the version guard working, not a pass).

- [ ] **Step 3: Implement the attribute in `toArray()`**

Replace the `toArray()` method body so it builds the base array and conditionally adds the metadata key:

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

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest --filter="route metadata"`
Expected on Laravel 13+: PASS (2 passed). On Laravel 11/12: SKIPPED.

- [ ] **Step 5: Run the full provider test file to confirm no regression**

Run: `vendor/bin/pest tests/AttributesProviders/LaravelRouteAttributesProviderTest.php`
Expected: all existing tests still PASS; the two new tests PASS (Laravel 13+) or SKIPPED (Laravel 11/12).

- [ ] **Step 6: Commit**

```bash
git add src/AttributesProviders/LaravelRouteAttributesProvider.php tests/AttributesProviders/LaravelRouteAttributesProviderTest.php
git commit -m "Add route metadata to Flare route attributes"
```

---

## Self-Review

- **Spec coverage:** Single integration point in `LaravelRouteAttributesProvider::toArray()` (Task 1, Step 3). Always-emit-on-13+ / omit-on-11-12 behaviour via `method_exists` (Step 3). Value attached as-is preserving nesting (test Step 1). Read through public `getMetadata()` (Step 3). Tests for metadata present, empty, and version skip (Step 1). `samplingAttributes()` unchanged (not touched). No client-package, sampling, recorder, or middleware changes (not touched). All spec sections covered.
- **Placeholder scan:** None. All steps contain concrete code and commands.
- **Type consistency:** `getMetadata()` returns `mixed` but with no key returns the full `array`; assigned to `laravel.route.metadata`. Test assertions use `toBe([...])` / `toBe([])` matching the array value. Consistent.

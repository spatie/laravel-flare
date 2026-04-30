# Entry Point Model Refactor

## Context

The entry point model (`flare.entry_point.type`, `flare.entry_point.value`) has three problems:

1. **Calculated too late.** Entry points are assembled during error report creation, scattered across providers and middleware. We need them right after routing for dynamic sampling decisions.
2. **Logs have no entry point.** Every log entry needs an entry point for filtering in Flare. There's no shared context for log entries to read from.
3. **The semantics are muddy.** `value` means full URL for web, joined args for CLI, job name for queue. `class` sometimes holds a class, a class+method, a blade view path, or null. The attribute names don't match what they contain.

## New model

Five attributes:

| Attribute | Purpose | Description |
|---|---|---|
| `flare.entry_point.type` | Execution context | `web`, `cli`, `queue` (unchanged) |
| `flare.entry_point.value` | Raw value | Full URL, full command with arguments, job class (unchanged, kept as-is) |
| `flare.entry_point.handler.identifier` | Groupable identifier | What you aggregate, sample, and search by |
| `flare.entry_point.handler.name` | What handles it | Controller, component, command class, job class, or null |
| `flare.entry_point.handler.type` | Kind of handler | Framework-prefixed type like `laravel_controller`, `php_closure` |

### Handler type values

Prefixed with language/framework for future cross-language support:

| handler_type | handler example | When |
|---|---|---|
| `laravel_controller` | `UsersController` | Invokable controller |
| `laravel_controller` | `BlogController@index` | Multi-action controller |
| `laravel_closure` | `routes/web.php` | Closure route |
| `laravel_view` | `users.index` | View route |
| `laravel_redirect` | `/new-path` | Redirect route |
| `livewire_component` | `App\Livewire\Dashboard` | Livewire class component |
| `livewire_sfc` | `pages.dashboard` | Livewire single-file component |
| `laravel_command` | `App\Console\Commands\WorkCommand` | Artisan command class |
| `laravel_job` | `App\Jobs\SendWelcomeEmail` | Queued job |
| `php_closure` | `Closure` | Closure job/command in vanilla PHP |
| `php_script` | `worker.php` | Plain PHP script |
| `php_function` | `handleRequest` | Function-based handler |

### Per-type values

| Type | value (raw) | handler_identifier (groupable) | handler | handler_type |
|---|---|---|---|---|
| Web (routed) | `https://example.com/users/123` | `GET /users/{userId}` | `UsersController` | `laravel_controller` |
| Web (multi-action) | `https://example.com/blog/hello` | `GET /blog/{slug}` | `BlogController@index` | `laravel_controller` |
| Web (closure) | `https://example.com/health` | `GET /health` | `routes/web.php` | `laravel_closure` |
| Web (view) | `https://example.com/welcome` | `GET /welcome` | `users.index` | `laravel_view` |
| Web (Livewire) | `https://example.com/dashboard` | `GET /dashboard` | `App\Livewire\Dashboard` | `livewire_component` |
| Web (Livewire SFC) | `https://example.com/dashboard` | `GET /dashboard` | `pages.dashboard` | `livewire_sfc` |
| Web (no router) | `https://example.com/users/123` | `GET /users/123` | null | null |
| CLI | `horizon:work --queue=high` | `horizon:work` | `App\Console\Commands\WorkCommand` | `laravel_command` |
| CLI (plain PHP) | `php worker.php --daemon` | `worker.php` | null | `php_script` |
| Queue | `App\Jobs\SendWelcomeEmail` | `SendWelcomeEmail` | `App\Jobs\SendWelcomeEmail` | `laravel_job` |
| Queue (closure) | `Closure` | `Closure` | null | `php_closure` |

## Release order

**Flare server first, then flare-client-php, then laravel-flare.**

The server must accept both old and new formats before clients start sending new data.

## Part 1: Flare server (flareapp.io)

### 1.0 Database migrations

**Migration: Postgres tables (`errors`, `error_occurrences`, `shared_errors`, `shared_error_occurrences`)**

For each of these four tables:

```php
// entry_point_type stays
$table->renameColumn('entry_point', 'entry_point_value');
$table->string('entry_point_value', 2048)->change();  // was varchar(255), now matches Str::limit
$table->string('entry_point_handler_identifier', 2048)->nullable();  // new: groupable identifier¬
$table->renameColumn('entry_point_class', 'entry_point_handler_name');
$table->string('entry_point_handler_type')->nullable();
```

`entry_point_type` and `has_multiple_entry_points` stay unchanged.

**Migration: Rename `entry_point_md5` generated column**

The `error_occurrences` and `shared_error_occurrences` tables have a generated column `entry_point_md5` defined as `MD5(entry_point)`. This must be dropped and recreated to reference the renamed column:

```php
// For error_occurrences and shared_error_occurrences:
$table->dropIndex('entry_point_insights_index');
$table->dropColumn('entry_point_md5');

// After the entry_point → entry_point_value rename:
$table->char('entry_point_value_md5', 32)->storedAs('MD5(entry_point_value)')->nullable();
$table->index(['error_id', 'entry_point_type', 'entry_point_value_md5'], 'entry_point_insights_index');
```

**Migration: Postgres span aggregation tables**

Rename and add columns in Postgres. The ClickHouse imported table also needs the column rename, but since data lives in Postgres this is straightforward:

```php
// Postgres: span_aggregations table
Schema::table('span_aggregations', function (Blueprint $table) {
    $table->renameColumn('flare_entry_point_class', 'flare_entry_point_handler_name');
});
```

```sql
-- ClickHouse (imported table)
ALTER TABLE span_aggregations RENAME COLUMN flare_entry_point_class TO flare_entry_point_handler_name;
```

**Migration: ClickHouse logs table**

The logs table has materialized columns `entry_point_type` and `entry_point_value`. Keep `entry_point_type`. Replace `entry_point_value` with `entry_point_handler_identifier` (route name, command name, or job name is more useful for log filtering than the raw URL):

```sql
ALTER TABLE logs DROP COLUMN IF EXISTS entry_point_value;
ALTER TABLE logs ADD COLUMN IF NOT EXISTS entry_point_handler_identifier String MATERIALIZED
    attributes['flare.entry_point.handler.identifier'];
```

**Update models:**
- `Error` model: rename `entry_point` → `entry_point_value`, rename `entry_point_class` → `entry_point_handler_name`, add `entry_point_handler_identifier`, `entry_point_handler_type`
- `ErrorOccurrence` model: same changes
- `SharedError` model: rename `entry_point` → `entry_point_value`

### 1.1 Update RawReport

**File:** `app/Domain/Error/Support/RawReport.php`

```php
public function entryPointType(): EntryPointType
{
    return EntryPointType::from($this->attributes['flare.entry_point.type'] ?? EntryPointType::Web->value);
}

public function entryPointValue(): ?string
{
    return Str::limit($this->attributes['flare.entry_point.value'] ?? null, 2048, '');
}

public function entryPointHandlerIdentifier(): ?string
{
    return Str::limit($this->attributes['flare.entry_point.handler.identifier'] ?? null, 2048, '');
}

public function entryPointHandlerName(): ?string
{
    return Str::limit($this->attributes['flare.entry_point.handler.name'] ?? null, 255, '');
}

public function entryPointHandlerType(): ?string
{
    return Str::limit($this->attributes['flare.entry_point.handler.type'] ?? null, 255, '');
}
```

### 1.2 Update ProcessRawReportAction

**File:** `app/Domain/Error/Actions/ProcessRawReportAction.php`

```php
'entry_point_value' => $rawReport->entryPointValue(),
'entry_point_handler_identifier' => $rawReport->entryPointHandlerIdentifier(),
'entry_point_type' => $rawReport->entryPointType(),
'entry_point_handler_name' => $rawReport->entryPointHandlerName(),
'entry_point_handler_type' => $rawReport->entryPointHandlerType(),
```

Same update in `fillErrorMetrics()`.

### 1.3 Update CreateNewErrorOccurrenceAction

**File:** `app/Domain/Error/Actions/CreateNewErrorOccurrenceAction.php`

```php
'entry_point_value' => $report->entryPointValue(),
'entry_point_handler_identifier' => $report->entryPointHandlerIdentifier(),
'entry_point_type' => $report->entryPointType(),
'entry_point_handler_name' => $report->entryPointHandlerName(),
'entry_point_handler_type' => $report->entryPointHandlerType(),
```

### 1.4 Update PhpRequestSpanAggregator

**File:** `app/Domain/Monitoring/SpanAggregators/PhpRequestSpanAggregator.php`

Read `flare.entry_point.handler.name` from span attributes:

```php
'flare.entry_point.handler.name' => $span->attributes['flare.entry_point.handler.name']
    ?? $span->attributes['laravel.route.action']
    ?? '',
```

### 1.5 Update SpanAggregationFilterType

**File:** `app/Domain/Monitoring/Enums/SpanAggregationFilterType.php`

Update label from "Controller" to "Handler" for routes.

### 1.6 Update SpanAggregationRouteDetail

**File:** `app/Domain/Monitoring/Data/SpanAggregationDetails/SpanAggregationRouteDetail.php`

Rename `$entry_point` → `$entry_point_handler_name`. Update `fromIngress()`, `fromDatabase()`, `toDatabase()` to use new attribute/column names (`flare.entry_point.handler.name` / `flare_entry_point_handler_name`).

### 1.7 Update ErrorCard.tsx

**File:** `resources/js/ui/ErrorCard.tsx`

Rename `entryPointClass` → `entryPointHandlerName`. Display: always show `entry_point_value` as primary text, with `entry_point_handler_name` underneath if present. For jobs, only show `entry_point_handler_name`:

```tsx
{entryPointHandlerIdentifier && (
    <p className="min-w-0 truncate text-xs">
        <span className="min-w-0 truncate whitespace-nowrap">
            {entryPointHandlerIdentifier}
        </span>
    </p>
)}
```

### 1.8 Update OccurrenceCard.tsx

**File:** `resources/js/ui/OccurrenceCard.tsx`

Rename `entry_point_class` → `entry_point_handler_name`. 

### 1.9 Update monitoring frontend components

These reference `detail.entry_point` (the handler). Rename to `detail.entry_point_handler_name`:

- `resources/js/domain/perf-monitoring/components/sp-agg-table/table-types/SpAggRoutes.tsx`
- `resources/js/domain/perf-monitoring/components/sp-agg-card/types/SpAggRouteCard.tsx`
- `resources/js/app/views/monitoring/Dashboard/modules/routes/RoutesTable.tsx`

### 1.10 Update ErrorOccurrence::occurredOn()

**File:** `app/Domain/Error/Models/ErrorOccurrence.php`

Use `entry_point_handler_identifier` with fallback to `entry_point_value`:

```php
public function occurredOn(): string
{
    $identifier = $this->entry_point_handler_name ?? $this->entry_point_value;

    return 'Occurred '.match ($this->entry_point_type) {
        EntryPointType::Cli => "in command {$identifier}",
        EntryPointType::Web => "on {$identifier}",
        EntryPointType::Queue => "in job {$identifier}",
        default => 'at an unknown point',
    };
}
```

### 1.11 Update TelegramErrorMessageFactory

**File:** `app/Domain/Error/Notifications/Telegram/TelegramErrorMessageFactory.php`

Update to use `entry_point_value` (renamed). Could optionally use `entry_point_handler_identifier` for cleaner display.

### 1.12 Update DTOs (auto-generates TypeScript types)

- `app/Domain/Error/Data/ErrorData.php`: rename `entry_point` → `entry_point_value`, rename `entry_point_class` → `entry_point_handler_name`, add `entry_point_handler_identifier`, `entry_point_handler_type`
- `app/Domain/Error/Data/ErrorOccurrenceData.php`: same changes

These DTOs use `#[TypeScript]`, so `resources/js/generated/types.d.ts` regenerates automatically. Run the TypeScript transformer before making any frontend changes.

### 1.13 Update API data classes

- `app/Http/Api/Resources/Data/ErrorApiData.php`: rename `entry_point` → `entry_point_value`, add `entry_point_handler_identifier`, `entry_point_handler_name`, `entry_point_handler_type`
- `app/Http/Api/Resources/Data/ErrorOccurrenceApiData.php`: same

The `entry_point_class` field is removed (replaced by `entry_point_handler_name`). Consider keeping `entry_point_class` as a deprecated alias during transition.

### 1.14 Update MapOldReportFormatAction

**File:** `app/Domain/Error/Actions/MapOldReportFormatAction.php`

Map old report format to new attribute names. `flare.entry_point.class` from old clients is mapped to `flare.entry_point.handler.name`.

### 1.15 Update search directives

- `app/Support/Search/Directives/EntryPointDirective.php`: consider searching against `entry_point_handler_identifier` for grouping-based search
- Search classes: update to use `entry_point_value` (renamed from `entry_point`)

### 1.16 Update log filter types

**File:** `app/Domain/Logging/Enums/LogFilterType.php`

Rename `EntryPoint = 'entry_point'` → `EntryPointValue = 'entry_point_value'`. Consider adding `EntryPointHandlerIdentifier` as a new filter type.

### 1.17 Update CSS classes

In ErrorCard.tsx and OccurrenceCard.tsx, rename CSS class `entryPointClass` → `entryPointHandlerName`.

### 1.18 Update Ignition.tsx

**File:** `resources/js/app/views/components/errors/Ignition.tsx`

Rename `entry_point` → `entry_point_value` in the mapping from `flareErrorOccurrence`:

```tsx
entry_point: flareErrorOccurrence.entry_point_value || '',
```

### 1.19 Update occurrence-markdown.blade.php

**File:** `resources/views/errors/occurrence-markdown.blade.php`

Rename `$errorOccurrence->entry_point` → `$errorOccurrence->entry_point_value`.

### 1.20 Update manual TypeScript type files

These are hand-maintained types (not auto-generated from DTOs):

- `resources/js/app/types/app.d.ts`: rename `entry_point: string` → `entry_point_value: string` in both `FlareError` (line 96) and `FlareErrorOccurrence` (line 176)
- `resources/js/app/ignition/types.ts`: rename `entry_point: string` → `entry_point_value: string` in `ErrorOccurrence` type (line 46)

### 1.21 Update insight classes

These group by the `entry_point` column (renamed to `entry_point_value`). Also update `entry_point_md5` → `entry_point_value_md5`:

- `app/Domain/Insights/UrlsInsight.php`: rename `entry_point` → `entry_point_value`, `entry_point_md5` → `entry_point_value_md5`
- `app/Domain/Insights/CommandInsight.php`: same
- `app/Domain/Insights/JobInsight.php`: same

### 1.22 Update shared error actions

**File:** `app/Domain/SharedError/Actions/ProcessSharedRawReportAction.php`

Rename `'entry_point'` → `'entry_point_value'` in the `SharedError::create()` call.

**File:** `app/Domain/SharedError/Actions/ShareErrorOccurrenceAction.php`

Rename `'entry_point_md5'` → `'entry_point_value_md5'` in the excluded columns list.

### 1.23 Update Filament admin panel

- `app/Filament/Resources/SharedErrorResource.php`: rename `entry_point` → `entry_point_value` in table column and globally searchable attributes
- `app/Filament/Resources/SharedErrorResource/Pages/ViewSharedError.php`: rename `entry_point` → `entry_point_value` in the TextEntry

### 1.24 Update test factories

- `tests/Factories/ErrorFactory.php`
- `tests/Factories/ErrorOccurrenceFactory.php`
- `tests/Factories/RawReportFactory.php`
- `database/factories/Domain/Logging/Rows/LogRowFactory.php`

### 1.25 Update tests

Rename `entry_point` → `entry_point_value` in test data and assertions. Rename `entry_point` → `entry_point_handler_name` in monitoring detail assertions:

- `tests/Feature/Domain/Error/Jobs/ProcessRawReportsJobTest.php`: rename `'entry_point'` key in test data
- `tests/Feature/Http/Api/Reporting/StorePublicRawReportsControllerTest.php`: same
- `tests/Http/Api/Controllers/Error/ErrorsIndexControllerTest.php`: rename `'entry_point'` in response assertions
- `tests/Http/Api/Controllers/Error/ErrorOccurrencesIndexController.php`: same
- `tests/Http/Api/Resources/Data/Monitoring/AggregationApiDataTest.php`: rename `entry_point` in detail assertions and test data
- `tests/Http/Api/Controllers/Monitoring/AggregationShowControllerTest.php`: rename `'entry_point'` in detail key assertions
- `tests/Http/Api/Controllers/Monitoring/AggregationIndexControllerTest.php`: same
- `tests/Http/Api/Controllers/Monitoring/MonitoringSummaryControllerTest.php`: same
- `tests/Http/Front/Controllers/ApiDocumentationControllerTest.php`: rename `'entry_point'` in API docs assertions
- `tests/Domain/Monitoring/Jobs/ProcessResourceSpansJobTest.php`: rename `->entry_point` to `->entry_point_handler_name`

### 1.26 Update documentation

**Protocol docs:**

- `resources/views/front/docs/protocol/errors/attributes.md`: Update the entry point attributes table. Remove `flare.entry_point.class`, add `flare.entry_point.handler.identifier`, `flare.entry_point.handler.name`, `flare.entry_point.handler.type`
- `resources/views/front/docs/protocol/errors/payload.md`: Update the example payload attributes to use the new attribute names
- `resources/views/front/docs/protocol/traces/aggregations.md`: Replace `flare.entry_point.class` with `flare.entry_point.handler.name` in the routes aggregation table

**Search docs:**

- `resources/views/front/docs/flare/errors/searching-errors.md`: Update search documentation to reflect that entry point search now covers `entry_point_value`, `entry_point_handler_identifier`, and `entry_point_handler_name`

### 1.27 No changes needed

- `ErrorCardProperties.tsx` - uses `entry_point_type` (unchanged)
- `EntryPointTypeInsight` - groups by `entry_point_type` (unchanged)
- `ProjectErrorOccurrenceStatisticsOntoErrorAction` - groups by `entry_point_type` (unchanged)
- `Error::getEntrypointInsight()` - matches on `entry_point_type` (unchanged)

## Part 2: flare-client-php

### Design decisions

**EntryPoint is progressively enriched.** `type` and `value` are always known at trace start (URL for web, argv for CLI). Handler properties (`handlerIdentifier`, `handlerName`, `handlerType`) are only known after routing/command resolution. The EntryPoint object uses a `$handlerResolved` flag to distinguish "not resolved yet" from "resolved to a value" (including null). This is necessary because `isset()` returns false for null values, so it can't distinguish uninitialized from explicitly-set-to-null for `?string $handlerName`.

**EntryPointResolver only detects type and value from the environment.** It answers: "Is this a web request or a CLI process? What is the raw URL or command?" No framework-specific logic. No handler resolution. The resolver does not need to be extended by framework clients.

**Recorders own handler resolution.** Each recorder (RoutingRecorder, CommandRecorder, JobRecorder) sets handler properties on the entry point at the moment the framework provides the information. They call overridable `resolveEntryPointHandlerIdentifier()`, `resolveEntryPointHandlerName()`, `resolveEntryPointHandlerType()` methods, so framework-specific packages (like laravel-flare) can override these for richer handler types (e.g., `laravel_controller`, `livewire_component`). The base package provides generic defaults.

**Recorders always boot.** RoutingRecorder, CommandRecorder, and JobRecorder are core infrastructure, not optional collect types. They always register event listeners regardless of `collects` configuration. Span creation remains guarded by `$this->tracer->isSampling()`, so no unnecessary tracing work happens when tracing is disabled. Entry point enrichment runs unconditionally because it is needed for logs and error reports even without tracing.

**EntryPoint replaces `samplerContext`.** The Sampler interface changes from `shouldSample(array $context)` to `shouldSample(EntryPoint $entryPoint)`. This eliminates the untyped array and ensures the sampler always has structured entry point data. This is a breaking change for custom `Sampler` implementations (see upgrade guide).

**Two-phase sampling (future work).** The initial sampling decision uses type+value from the resolver (URL, command string). When a recorder later resolves the handler, it can resample based on handler-specific rules (e.g., "don't sample this route"). If the rule says "don't sample", call `tracer->unsample()` to discard accumulated spans. This mechanism already exists (CommandRecorder uses `unsample()` for ignored commands). The details of handler-based resampling will be designed in a future iteration. For now, only the initial type+value sampling phase is implemented.

**Entry point attributes flow through one channel: the EntryPointResolver.** The AttributesProviders (Request, Console) no longer set `flare.entry_point.*` attributes. Instead:
- **Logs**: Logger merges `$resolver->get()->toAttributes()`.
- **Errors**: A new `AddEntryPoint` middleware merges `$resolver->get()->toAttributes()` onto every report.
- **Traces**: Each recorder that creates the entry point span (RequestRecorder, CommandRecorder, JobRecorder) merges `$resolver->get()->toAttributes()` into the span attributes.

**New base JobRecorder.** A base `JobRecorder` in flare-client-php provides three methods: `recordStart()`, `recordEnd()`, `recordFailed()`. It handles entry point setup (type=Queue, replaces the resolver's entry point), span creation, and handler resolution via overridable methods. The Laravel version overrides to add payload extraction, chaining, batching, traceparent propagation, lifecycle subtask management, and richer handler types.

### 2.1 Create `EntryPoint` value object

**New file:** `src/EntryPoint/EntryPoint.php`

```php
class EntryPoint
{
    public bool $handlerResolved = false;

    public string $handlerIdentifier;
    public ?string $handlerName;
    public ?string $handlerType;

    public function __construct(
        public readonly EntryPointType $type,
        public string $value,
    ) {}

    public function updateValue(string $value): void
    {
        $this->value = $value;
    }

    public function setHandler(
        string $handlerIdentifier,
        ?string $handlerName,
        ?string $handlerType,
    ): void {
        $this->handlerIdentifier = $handlerIdentifier;
        $this->handlerName = $handlerName;
        $this->handlerType = $handlerType;
        $this->handlerResolved = true;
    }

    /** @return array<string, string|null> */
    public function toAttributes(): array
    {
        $attributes = [
            'flare.entry_point.type' => $this->type->value,
            'flare.entry_point.value' => $this->value,
        ];

        if ($this->handlerResolved) {
            $attributes['flare.entry_point.handler.identifier'] = $this->handlerIdentifier;
            $attributes['flare.entry_point.handler.name'] = $this->handlerName;
            $attributes['flare.entry_point.handler.type'] = $this->handlerType;
        }

        return $attributes;
    }
}
```

`type` is readonly (immutable once constructed). `value` has an explicit `updateValue()` method for cases where the value needs to change after construction (e.g., Livewire replacing `POST /livewire/update` with the original page URL). Handler properties are set together via `setHandler()`, which flips `$handlerResolved`. `toAttributes()` only includes handler attributes after resolution, so logs and reports before routing get type+value only.

### 2.2 Create `EntryPointResolver`

**New file:** `src/EntryPoint/EntryPointResolver.php`

Stores the current entry point. Auto-detects type and value from the PHP environment when none is set. Does not resolve handler properties (that is the recorders' responsibility).

```php
class EntryPointResolver
{
    protected ?EntryPoint $entryPoint = null;

    public function get(): EntryPoint
    {
        return $this->entryPoint ??= $this->resolve();
    }

    public function set(EntryPoint $entryPoint): void
    {
        $this->entryPoint = $entryPoint;
    }

    public function clear(): void
    {
        $this->entryPoint = null;
    }

    protected function resolve(): EntryPoint
    {
        if (Runtime::runningInConsole()) {
            return new EntryPoint(
                type: EntryPointType::Cli,
                value: implode(' ', $_SERVER['argv'] ?? []),
            );
        }

        $scheme = ($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return new EntryPoint(
            type: EntryPointType::Web,
            value: "{$scheme}://{$host}{$uri}",
        );
    }
}
```

- `get()` always returns an `EntryPoint`, never null. Uses `??=` to auto-detect and cache on first access.
- `set()` explicitly replaces the entry point (e.g., JobRecorder replaces with type=Queue since a queue worker would otherwise auto-detect as CLI).
- `clear()` resets to null. Called by Lifecycle between requests/jobs. Next `get()` auto-detects again.
- `resolve()` only detects web vs CLI from `$_SERVER`. No framework-specific logic.

Register in `FlareProvider` as a singleton.

### 2.3 Update Sampler interface

**Breaking change.** Custom `Sampler` implementations must update their signature. Add to upgrade guide.

**File:** `src/Sampling/Sampler.php`

```php
use Spatie\FlareClient\EntryPoint\EntryPoint;

interface Sampler
{
    public function shouldSample(EntryPoint $entryPoint): bool;
}
```

**Upgrade guide entry:**

> The `Sampler` interface signature changed from `shouldSample(array $context)` to `shouldSample(EntryPoint $entryPoint)`. If you have a custom sampler, update the method signature. The `EntryPoint` object provides `$entryPoint->type` (EntryPointType enum), `$entryPoint->value` (raw URL/command/job), and handler properties when resolved.

### 2.4 Update sampler implementations

Update all three built-in sampler implementations:

**File:** `src/Sampling/RateSampler.php`

```php
public function shouldSample(EntryPoint $entryPoint): bool
```

Parameter renamed but logic unchanged (rate-based, ignores entry point).

**File:** `src/Sampling/AlwaysSampler.php`

```php
public function shouldSample(EntryPoint $entryPoint): bool
```

**File:** `src/Sampling/NeverSampler.php`

```php
public function shouldSample(EntryPoint $entryPoint): bool
```

### 2.5 Update Tracer

**File:** `src/Tracer.php`

Inject `EntryPointResolver` via the constructor.

Update `startTrace()`: remove `array $samplerContext = []`. Use `$this->entryPointResolver->get()` for the sampler:

```php
public function startTrace(
    ?string $traceId = null,
    ?string $spanId = null,
    ?bool $sample = null,
    ?string $traceParent = null
): bool {
    if ($this->disabled) {
        return false;
    }

    if ($this->sampling) {
        return $this->sampling;
    }

    if ($traceParent) {
        return $this->startFromTraceparent($traceParent);
    }

    if ($traceId && $spanId && $sample !== null) {
        return $this->startFromDefined(
            sample: $sample,
            traceId: $traceId,
            spanId: $spanId,
            currentSpanIdAvailable: false,
        );
    }

    if ($traceId || $spanId || $sample !== null) {
        throw new Exception("If one of traceId, spanId or sample is provided, all three must be provided.");
    }

    return $this->sampling = $this->sampler->shouldSample($this->entryPointResolver->get());
}
```

Keep `startFromTraceparent()` as a separate method. Its fallback (when traceparent parsing fails) recurses into `startTrace()`, which reaches the sampler via the resolver. No changes needed there.

### 2.6 Update Lifecycle

**File:** `src/Support/Lifecycle.php`

Inject `EntryPointResolver`. Remove `shouldPotentiallySampleTrace()` method and the `$shouldMakeSamplingDecisionClosure` constructor parameter. These were never wired up (always null) and sampling decisions are now handled by the Sampler via EntryPoint.

Update `start()`: remove `array $samplerContext = []`. The resolver already has the entry point (auto-detected on first `get()`):

```php
public function start(
    ?int $timeUnixNano = null,
    array $attributes = [],
    ?string $traceparent = null,
): void {
    // ... unchanged guards ...

    $this->stage = LifecycleStage::Started;

    $this->tracer->startTrace(traceParent: $traceparent);

    // ... application span creation ...
}
```

Update `startSubtask()`: same, remove `array $samplerContext = []`:

```php
public function startSubtask(
    ?string $traceparent = null,
): void {
    // ... unchanged guards ...

    $this->stage = LifecycleStage::Subtask;

    $this->tracer->startTrace(traceParent: $traceparent);
}
```

Update `endSubtask()` and `flush()`: clear the resolver:

```php
// In endSubtask(), after endTrace():
$this->entryPointResolver->clear();

// In flush():
$this->entryPointResolver->clear();
```

### 2.7 Remove `shouldMakeSamplingDecisionClosure`

**File:** `src/FlareProvider.php`

Remove the `$shouldMakeSamplingDecisionClosure` constructor parameter. It was never set by any caller (always null) and the feature it was intended for is unreleased.

**File:** `src/Support/Lifecycle.php`

Remove the `$shouldMakeSamplingDecisionClosure` constructor parameter and the `shouldPotentiallySampleTrace()` method.

**File (Part 3):** `src/FlareServiceProvider.php` (laravel-flare)

Remove the `$shouldMakeSamplingDecisionClosure` constructor parameter.

### 2.8 Add entry point to Logger

**File:** `src/Logger.php`

Inject `EntryPointResolver`. After the context recorder merge (line 94), merge entry point attributes:

```php
$attributes = [...$attributes ?? [], ...$this->entryPointResolver->get()->toAttributes()];
```

### 2.9 Remove entry point attributes from RequestAttributesProvider

**File:** `src/AttributesProviders/RequestAttributesProvider.php`

Remove the three `flare.entry_point.*` attributes entirely. Entry point data now flows through the resolver, not the providers:

```php
// Remove these lines:
'flare.entry_point.type' => EntryPointType::Web->value,
'flare.entry_point.value' => $request->getUri(),
'flare.entry_point.class' => null,
```

### 2.10 Remove entry point attributes from ConsoleAttributesProvider

**File:** `src/AttributesProviders/ConsoleAttributesProvider.php`

Remove the three `flare.entry_point.*` attributes:

```php
// Remove these lines:
'flare.entry_point.type' => EntryPointType::Cli,
'flare.entry_point.value' => implode(' ', $arguments),
'flare.entry_point.class' => null,
```

### 2.11 Update RoutingRecorder

**File:** `src/Recorders/RoutingRecorder/RoutingRecorder.php`

Inject `EntryPointResolver` via the constructor. Add entry point handler resolution in `recordRoutingEnd()`. The base implementation reads `http.route` from the span attributes (a documented attribute that frameworks set) and combines it with the HTTP method for the handler identifier. Handler name and type default to null in the base package (framework-specific packages override for richer types):

```php
public function recordRoutingEnd(
    array $attributes = [],
    ?int $time = null
): ?Span {
    if ($this->routing === false) {
        return null;
    }

    $this->routing = false;

    $span = $this->endSpan(
        time: $time,
        additionalAttributes: $attributes,
    );

    $this->enrichEntryPoint();

    return $span;
}

protected function enrichEntryPoint(): void
{
    $entryPoint = $this->entryPointResolver->get();

    if ($entryPoint->handlerResolved) {
        return;
    }

    $entryPoint->setHandler(
        handlerIdentifier: $this->resolveEntryPointHandlerIdentifier(),
        handlerName: $this->resolveEntryPointHandlerName(),
        handlerType: $this->resolveEntryPointHandlerType(),
    );
}

protected function resolveEntryPointHandlerIdentifier(): string
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $route = $this->lastResolvedRoute; // stored from span attributes

    return "{$method} /{$route}";
}

protected function resolveEntryPointHandlerName(): ?string
{
    return null;
}

protected function resolveEntryPointHandlerType(): ?string
{
    return null;
}
```

The `resolveEntryPointHandler*` methods provide generic defaults. The `http.route` attribute is read from the span that was just ended (or from whatever route information was passed to the recorder). Framework packages override these three methods to return richer values.

### 2.12 Update CommandRecorder

**File:** `src/Recorders/CommandRecorder/CommandRecorder.php`

Inject `EntryPointResolver` via the constructor. Remove the `$entryPointClass` parameter and `flare.entry_point_class` attribute (was a bug anyway, used underscore instead of dot). Enrich the entry point in `recordStart()` since the command name is known immediately. Merge entry point attributes into the command span:

```php
public function recordStart(
    string $command,
    array|InputInterface $arguments,
    array $attributes = []
): ?Span {
    $this->currentCommand = $command;

    $this->enrichEntryPoint();

    return $this->startSpan(
        name: "Command - {$command}",
        attributes: function () use ($attributes, $arguments, $command) {
            if ($arguments instanceof InputInterface) {
                $arguments = $this->getArguments($arguments);
            }

            return [
                'flare.span_type' => SpanType::Command,
                'process.command' => $command,
                'process.command_args' => $arguments,
                ...$this->entryPointResolver->get()->toAttributes(),
                ...$attributes,
            ];
        },
    );
}

protected function enrichEntryPoint(): void
{
    $entryPoint = $this->entryPointResolver->get();

    if ($entryPoint->handlerResolved) {
        return;
    }

    $entryPoint->setHandler(
        handlerIdentifier: $this->resolveEntryPointHandlerIdentifier(),
        handlerName: $this->resolveEntryPointHandlerName(),
        handlerType: $this->resolveEntryPointHandlerType(),
    );
}

protected function resolveEntryPointHandlerIdentifier(): string
{
    return $this->currentCommand;
}

protected function resolveEntryPointHandlerName(): ?string
{
    return null;
}

protected function resolveEntryPointHandlerType(): ?string
{
    return null;
}
```

The `handlerResolved` guard prevents nested commands (e.g., `deploy` calling `migrate`) from overwriting the original entry point.

### 2.13 Create base JobRecorder

**New file:** `src/Recorders/JobRecorder/JobRecorder.php`

A base job recorder with three methods: `recordStart()`, `recordEnd()`, `recordFailed()`. Handles entry point setup (type=Queue, replaces the resolver's entry point since auto-detection would give type=Cli for queue workers) and span creation. Handler resolution via overridable `resolveEntryPointHandler*` methods.

```php
class JobRecorder extends SpansRecorder
{
    public static function type(): string|RecorderType
    {
        return RecorderType::Job;
    }

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected EntryPointResolver $entryPointResolver,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public function recordStart(
        string $jobName,
        ?string $jobClass = null,
        array $attributes = [],
    ): ?Span {
        $this->currentJobName = $jobName;
        $this->currentJobClass = $jobClass;

        $entryPoint = new EntryPoint(
            type: EntryPointType::Queue,
            value: $jobClass ?? $jobName,
        );

        $this->entryPointResolver->set($entryPoint);

        $entryPoint->setHandler(
            handlerIdentifier: $this->resolveEntryPointHandlerIdentifier(),
            handlerName: $this->resolveEntryPointHandlerName(),
            handlerType: $this->resolveEntryPointHandlerType(),
        );

        return $this->startSpan(
            name: "Job - {$jobName}",
            attributes: [
                'flare.span_type' => SpanType::Job,
                ...$this->entryPointResolver->get()->toAttributes(),
                ...$attributes,
            ],
        );
    }

    public function recordEnd(array $attributes = []): ?Span
    {
        return $this->endSpan(
            additionalAttributes: $attributes,
            includeMemoryUsage: true,
        );
    }

    public function recordFailed(
        Throwable $exception,
        array $attributes = [],
    ): ?Span {
        $throwableClass = $exception::class;

        return $this->endSpan(
            additionalAttributes: $attributes,
            spanCallback: fn (Span $span) => $span
                ->setStatus(SpanStatusCode::Error, $exception->getMessage())
                ->addEvent(new SpanEvent(
                    name: "Exception - {$throwableClass}",
                    timestamp: $this->tracer->time->getCurrentTime(),
                    attributes: [
                        'flare.span_event_type' => SpanEventType::Exception,
                        'exception.message' => $exception->getMessage(),
                        'exception.type' => $throwableClass,
                    ],
                )),
            includeMemoryUsage: true,
        );
    }

    protected function resolveEntryPointHandlerIdentifier(): string
    {
        return $this->currentJobName;
    }

    protected function resolveEntryPointHandlerName(): ?string
    {
        return $this->currentJobClass;
    }

    protected function resolveEntryPointHandlerType(): ?string
    {
        if ($this->currentJobClass !== null) {
            return null; // framework packages override to return 'laravel_job', etc.
        }

        if ($this->currentJobName === 'Closure') {
            return 'php_closure';
        }

        return null;
    }
}
```

The base handles the common structure. Framework packages override the recorder and `resolveEntryPointHandler*` methods for richer attributes (payload extraction, chaining, batching, lifecycle subtask management).

### 2.14 Update RequestRecorder

**File:** `src/Recorders/RequestRecorder/RequestRecorder.php`

Inject `EntryPointResolver` via the constructor. Remove the `$entryPointClass` parameter from `recordStart()`. Merge entry point attributes in `recordEnd()` instead of `recordStart()`, since the handler is only resolved after routing:

```php
public function recordEnd(
    ?Response $response = null,
    array $attributes = [],
): ?Span {
    if ($response) {
        $responseAttributes = $this->responseAttributesProvider->toArray($response);

        $attributes = [...$attributes, ...$responseAttributes];
    }

    return $this->endSpan(additionalAttributes: [
        ...$this->entryPointResolver->get()->toAttributes(),
        ...$attributes,
    ], includeMemoryUsage: true);
}
```

### 2.15 Create AddEntryPoint middleware

**New file:** `src/FlareMiddleware/AddEntryPoint.php`

A single middleware that adds entry point attributes to every error report, regardless of entry point type (web, cli, queue):

```php
class AddEntryPoint implements FlareMiddleware
{
    public function __construct(
        protected EntryPointResolver $entryPointResolver,
    ) {}

    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        $report->addAttributes($this->entryPointResolver->get()->toAttributes());

        return $next($report);
    }
}
```

No null check needed since `get()` always returns an `EntryPoint` (auto-detects if none was set). Register this middleware as the first middleware in `FlareProvider` so entry point attributes are available to all subsequent middleware. Remove entry point merging from `AddRequestInformation` and `AddConsoleInformation` since they no longer handle it.

### 2.16 Update TrimAttributesStrategy

**File:** `src/Truncation/TrimAttributesStrategy.php`

Replace `flare.entry_point.class` with new handler attributes in the protected list:

```php
'flare.entry_point.type',
'flare.entry_point.value',
'flare.entry_point.handler.identifier',
'flare.entry_point.handler.name',
'flare.entry_point.handler.type',
```

### 2.17 Update FlareProvider

**File:** `src/FlareProvider.php`

Register `EntryPointResolver` as a singleton. Inject it into `Tracer`, `Logger`, `Lifecycle`, and recorder factories. Remove `$shouldMakeSamplingDecisionClosure` from the constructor.

Always register RoutingRecorder, CommandRecorder, and JobRecorder regardless of `collects` config. These are core infrastructure for entry point detection. In the base `CollectsResolver`, ensure these three recorders are always included. Their span creation is guarded by `$this->tracer->isSampling()`, so no tracing overhead when tracing is disabled.

### 2.18 Update Tester

**File:** `src/Support/Tester.php`

Replace `flare.entry_point.class` in test trace:

```php
'flare.entry_point.handler.identifier' => 'GET /test-flare-integration',
'flare.entry_point.handler.name' => self::class,
'flare.entry_point.handler.type' => 'php_class',
```

### 2.19 Update tests

- `tests/AttributesProviders/ConsoleAttributesProviderTest.php`: remove entry point attributes from expected output
- `tests/__snapshots__/RequestAttributesProviderTest__*.yml`: remove `flare.entry_point.*` lines
- Update sampler tests for new `shouldSample(EntryPoint)` signature
- New test for `EntryPoint` value object: construction, `updateValue()`, progressive enrichment via `setHandler()`, `toAttributes()` before and after handler resolution
- New test for `EntryPointResolver`: `get()` auto-detects, `set()` overrides, `clear()` resets, `get()` after clear re-detects
- New test for `Logger`: entry point attributes included in log records
- New test for `AddEntryPoint` middleware: merges entry point attributes onto report, works even without `startTrace()`
- New test for base `JobRecorder`: `recordStart()` sets entry point, `recordEnd()` succeeds, `recordFailed()` adds exception event
- New test for `CommandRecorder`: entry point enriched on `recordStart()`, nested commands don't overwrite

## Part 3: laravel-flare

### 3.1 Always boot recorders for entry point detection

**File:** `src/Support/CollectsResolver.php`

Always add `RoutingRecorder`, `CommandRecorder`, and `JobRecorder` in `resolve()`, regardless of `collects` config. They are core infrastructure for entry point detection, not optional collect types. Their span creation is already guarded by `$this->tracer->isSampling()`.

### 3.2 Update FlareServiceProvider

**File:** `src/FlareServiceProvider.php`

Remove `$shouldMakeSamplingDecisionClosure` from the constructor.

`Lifecycle::start()` and `startSubtask()` no longer take `samplerContext`. The resolver auto-detects type+value from the environment. Recorders handle handler enrichment.

No need to register a `LaravelEntryPointResolver`. The base `EntryPointResolver` handles web vs CLI detection, and all handler resolution is done by the recorder overrides in this package.

### 3.3 Override RoutingRecorder handler resolution

**File:** `src/Recorders/RoutingRecorder/RoutingRecorder.php`

The Laravel RoutingRecorder already listens to `RouteMatched`. It calls `recordRoutingEnd()` which triggers `enrichEntryPoint()` in the base. Override the `resolveEntryPointHandler*` methods to provide Laravel-specific handler types. Store the matched route from the `RouteMatched` event:

```php
$this->dispatcher->listen(RouteMatched::class, function (RouteMatched $event) {
    $this->matchedRoute = $event->route;
    $this->matchedRequest = $event->request;

    $this->recordRoutingEnd();
    $this->recordBeforeMiddlewareStart();
});

protected function resolveEntryPointHandlerIdentifier(): string
{
    return strtoupper($this->matchedRequest->getMethod())
        . ' /' . ltrim($this->matchedRoute->uri(), '/');
}

protected function resolveEntryPointHandlerName(): ?string
{
    return $this->resolveRouteAction()[0];
}

protected function resolveEntryPointHandlerType(): ?string
{
    return $this->resolveRouteAction()[1];
}

/** @return array{?string, ?string} [handlerName, handlerType] */
protected function resolveRouteAction(): array
{
    $actionName = $this->matchedRoute->getActionName();

    if ($actionName === '\\' . ViewController::class) {
        $view = $this->matchedRoute->parameter('view');
        return [is_string($view) ? $view : null, 'laravel_view'];
    }

    if ($actionName === '\\' . RedirectController::class) {
        $destination = $this->matchedRoute->parameter('destination');
        return [is_string($destination) ? $destination : null, 'laravel_redirect'];
    }

    if ($actionName === 'Closure' && $this->matchedRoute->getAction('uses') instanceof Closure) {
        $reflection = new ReflectionFunction($this->matchedRoute->getAction('uses'));
        $filename = str_replace(
            rtrim(base_path(), '/') . '/',
            '',
            $reflection->getFileName() ?: 'unknown',
        );
        return [$filename, 'laravel_closure'];
    }

    return [$actionName, 'laravel_controller'];
}
```

This logic currently lives in `LaravelRequestAttributesProvider::getActionAttributes()`. It moves here because the RoutingRecorder is the component that knows when the route is matched.

### 3.4 Override CommandRecorder handler resolution

**File:** `src/Recorders/CommandRecorder/CommandRecorder.php`

Override `resolveEntryPointHandlerName()` and `resolveEntryPointHandlerType()` to resolve the command class from Artisan:

```php
protected function resolveEntryPointHandlerName(): ?string
{
    $command = Artisan::all()[$this->currentCommand] ?? null;

    return $command ? get_class($command) : null;
}

protected function resolveEntryPointHandlerType(): ?string
{
    return $this->resolveEntryPointHandlerName() ? 'laravel_command' : null;
}
```

The `handlerResolved` guard in the base `enrichEntryPoint()` prevents nested commands (e.g., `deploy` calling `migrate` inline) from overwriting the original entry point.

### 3.5 Override JobRecorder

**File:** `src/Recorders/JobRecorder/JobRecorder.php`

The Laravel JobRecorder extends the base to add:
- Listening to `JobProcessing`, `JobProcessed`, `JobExceptionOccurred` events
- Traceparent propagation from job payload
- Lifecycle subtask management (`startSubtask()`/`endSubtask()`)
- Laravel-specific job attributes (payload, chaining, batching via `LaravelJobAttributesProvider`)
- `AddJobInformation` breadcrumb support for error reports
- Ignore list for internal jobs

```php
public function recordProcessing(JobProcessing $event): ?Span
{
    AddJobInformation::clearLatestJobInfo();

    $traceparent = $event->job->payload()[Ids::FLARE_TRACE_PARENT] ?? null;

    $shouldIgnore = $this->shouldIgnore($event->job);

    if ($shouldIgnore) {
        $traceparent = $this->tracer->ids->setTraceparentSampling($traceparent, false);
    }

    // Set entry point before starting subtask
    $attributes = $this->laravelJobAttributesProvider->toArray(
        $event->job,
        $event->connectionName,
        $this->maxChainedJobReportingDepth
    );

    $jobName = $attributes['laravel.job.name'] ?? $attributes['laravel.job.class'] ?? 'Unknown';
    $jobClass = $attributes['laravel.job.class'] ?? null;

    // Start subtask (which starts a new trace)
    $this->lifecycle->startSubtask(traceparent: $traceparent);

    if ($shouldIgnore) {
        return null;
    }

    // recordStart handles entry point setup + span creation
    return parent::recordStart(
        jobName: $jobName,
        jobClass: $jobClass,
        attributes: [
            ...$attributes,
        ],
    );
}

protected function resolveEntryPointHandlerType(): ?string
{
    if ($this->currentJobClass !== null) {
        return 'laravel_job';
    }

    if ($this->currentJobName === 'Closure') {
        return 'php_closure';
    }

    return null;
}
```

`recordProcessed()` calls `parent::recordEnd()` then `$this->lifecycle->endSubtask()`. `recordExceptionOccurred()` calls `parent::recordFailed()` with additional breadcrumb logic then `$this->lifecycle->endSubtask()`.

### 3.6 Remove entry point attributes from LaravelRequestAttributesProvider

**File:** `src/AttributesProviders/LaravelRequestAttributesProvider.php`

Remove `flare.entry_point.class` from `getActionAttributes()`. Keep `laravel.route.action` and `laravel.route.action_type` unchanged (they serve a different purpose than entry point handler). The action resolution logic (`getActionAttributes()`) for entry points now lives in `RoutingRecorder::resolveRouteAction()`.

### 3.7 Update LivewireAttributesProvider

**File:** `src/AttributesProviders/LivewireAttributesProvider.php`

Remove `flare.entry_point.value` from the output array. Instead, update the entry point on the resolver. Inject `EntryPointResolver` into the provider. `POST /livewire/update` is an implementation detail, so we replace the value and handler with the original page URL and Livewire component:

```php
public function toArray(
    Request $request,
    LivewireManager $livewire,
    array $ignore = [],
): array {
    $entryPoint = $this->entryPointResolver->get();

    $entryPoint->updateValue($livewire->originalUrl());

    // Determine component class and whether it's an SFC
    $components = $this->getLivewire($request, $livewire, $ignore);
    $componentClass = $components[0]['component_class'] ?? null;
    $componentName = $components[0]['component_alias']
        ?? $components[0]['memo']['name']
        ?? null;
    $isSfc = $componentClass === null && $componentName !== null;

    $entryPoint->setHandler(
        handlerIdentifier: strtoupper($livewire->originalMethod())
            . ' ' . parse_url($livewire->originalUrl(), PHP_URL_PATH),
        handlerName: $componentClass ?? $componentName,
        handlerType: $isSfc ? 'livewire_sfc' : 'livewire_component',
    );

    return [
        'http.request.method' => $livewire->originalMethod(),
        'url.full' => $livewire->originalUrl(),
        'url.scheme' => parse_url($livewire->originalUrl(), PHP_URL_SCHEME),
        'url.path' => parse_url($livewire->originalUrl(), PHP_URL_PATH),
        'url.query' => parse_url($livewire->originalUrl(), PHP_URL_QUERY),
        'livewire.components' => $components,
    ];
}
```

Note: `updateValue()` changes the URL on the existing entry point rather than replacing the entire entry point via `set()`. The type stays `web`. The handler is overwritten from the `POST /livewire/update` handler (set by `RoutingRecorder`) to the actual component.

### 3.8 Update AddJobInformation middleware

**File:** `src/FlareMiddleware/AddJobInformation.php`

Remove the manual `flare.entry_point.*` attribute setting. The `AddEntryPoint` middleware (from the base package) now handles this via the resolver. `AddJobInformation` still handles:
- Attaching the latest job span to the report (`$report->span($latestJob)`)
- Setting the tracking UUID for error breadcrumbs

```php
public function handle(ReportFactory $report, Closure $next): ReportFactory
{
    if ($latestJob = static::$latestJob) {
        $report->span($latestJob);
        static::$latestJob = null;
    }

    if (static::$usedTrackingUuid) {
        $report->trackingUuid(static::$usedTrackingUuid);
        static::$usedTrackingUuid = null;
    }

    return $next($report);
}
```

### 3.9 Update integration tests

**File:** `tests/IntegrationTest.php`

- Assert `flare.entry_point.handler.identifier` format (`GET /invokable-controller`, etc.)
- Assert `flare.entry_point.handler.name` = `InvokableController::class`, `ResourceController::class.'@index'`
- Assert `flare.entry_point.handler.type` = `'laravel_controller'`
- Assert Livewire entry points use `livewire_component`/`livewire_sfc` handler types with original URL as value
- Assert CLI commands use `laravel_command` handler type
- Assert queue jobs use `laravel_job` handler type
- Verify `flare.entry_point.value` still contains the full URL for web requests
- Verify entry point attributes appear on log records
- Verify entry point attributes appear on error reports even without tracing enabled

## Edge cases

1. **Livewire `POST /livewire/update`**: `RouteMatched` fires, `RoutingRecorder::recordRoutingEnd()` enriches the entry point with `POST /livewire/update` handler. `LivewireAttributesProvider` then calls `updateValue()` to fix the URL and `setHandler()` to replace the handler with the component. This is correct because `POST /livewire/update` is an implementation detail identical for all Livewire requests. The original page URL is what matters for filtering and grouping.
2. **Queue subtasks**: `JobRecorder` creates a new entry point with type=Queue and calls `$resolver->set()` before `startSubtask()`. `endSubtask()` calls `resolver->clear()`. Next job creates a fresh entry point. Flows correctly.
3. **Octane**: Each request triggers `resolver->clear()` via `flush()`. Next request auto-detects a fresh entry point. No issues.
4. **404/no route match**: `RouteMatched` never fires. Resolver's entry point has type=web and value=URL (auto-detected), but handler properties stay uninitialized. `toAttributes()` returns only type+value. No `handler.identifier`, `handler.name`, or `handler.type` on the report or span.
5. **No Lifecycle (vanilla PHP user)**: User never calls `startTrace()` or Lifecycle methods. When an error occurs, `AddEntryPoint` middleware calls `resolver->get()` which auto-detects from `$_SERVER`. Logger does the same. Entry point is always available.
6. **Legacy data in Flare**: `entry_point_value` column (renamed from `entry_point`) keeps full URLs. New `entry_point_handler_identifier` column will be null for old data. `entry_point_handler_name` column (renamed from `entry_point_class`) keeps existing values. No data loss.
7. **Nested commands**: If command `deploy` calls `migrate` inline, the base `CommandRecorder::enrichEntryPoint()` checks `$entryPoint->handlerResolved` and skips enrichment. The original `deploy` handler stays.
8. **Recorders booted without tracing**: When tracing is disabled, recorders still boot and enrich the entry point. Span creation calls return null (guarded by `isSampling()`). Entry point attributes are still available for logs and error reports via the resolver.

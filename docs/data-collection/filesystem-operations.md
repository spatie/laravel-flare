---
title: Filesystem operations 
---


Flare can collect information about the filesystem operations that your application executes.

This functionality is enabled by default, but you can disable it by ignoring the `Filesystem` collect in `config.php`:

```php
use Spatie\FlareClient\Enums\CollectType;

'collects' => FlareConfig::defaultCollects(
    ignore: [CollectType::Filesystem],
),
```

By default, the functionality is opt-in per disk. In order to enable it for a disk, you should add `flare => true` to the disk configuration in `config/filesystems.php`:

```php
'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
        'flare' => true,
    ],
],
```

You can also enable it for all disks by adding the `track_all_disks => true` option to the Flare filesystem collector in the `flare.php` config file:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Filesystem->value => [
            'track_all_disks' => true,
        ],
    ]
),
```

You can configure the maximum number of filesystem operations tracked while collecting data in the case of an error as such:

```php
'collects' => FlareConfig::defaultCollects(
    extra: [
        CollectType::Filesystem->value => [
            'max_items_with_errors' => 10,
        ],
    ]
),
```

## Manually recording filesystem operations

If you're performing filesystem operations outside of Laravel's Storage facade, you can record them manually. The [PHP documentation](/docs/php/data-collection/filesystem-operations) provides a full overview of all available recorder methods. When using these methods in Laravel, use the `Flare` facade instead of `$flare`:

```php
use Spatie\LaravelFlare\Facades\Flare;

Flare::filesystem()->recordGet('/path/to/file.txt');

// ... perform the operation

Flare::filesystem()->recordOperationEnd();
```

In addition to all the methods documented in the PHP documentation, the Laravel package provides these extra recorder methods:

- `recordGetVisibility(string $path)`
- `recordSetVisibility(string $path, string $visibility)`
- `recordLastModified(string $path)`
- `recordChecksum(string $path)`
- `recordMimeType(string $path)`
- `recordTemporaryUrl(string $path, DateTimeInterface $expiration)`
- `recordTemporaryUploadUrl(string $path, DateTimeInterface $expiration)`

Each of these should be followed by a `recordOperationEnd()` call when the operation completes. 
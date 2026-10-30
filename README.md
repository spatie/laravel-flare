# Laravel Flare: send Laravel errors to Flare

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-flare.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-flare)
![Tests](https://github.com/spatie/laravel-flare/workflows/Run%20tests/badge.svg)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-flare.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-flare)

[Laravel Flare](https://flareapp.io/docs/integration/laravel-customizations/introduction) allows to publicly share your errors on [Flare](https://flareapp.io). If configured with a valid Flare API key, your errors in production applications will be tracked, and you'll get notified when they happen.

`spatie/laravel-flare` works for Laravel 11 and above applications running on PHP 8.2 and above. Looking for Ignition for Laravel, the most beautiful error page out there? You can still install `spatie/laravel-ignition` and admire it.

## Official Documentation

The official documentation for Flare can be found on the [Flare website](https://flareapp.io/docs/integration/laravel-customizations/introduction).

### SQL connection timing

Query collection also records `Database connection` spans when Laravel resolves a lazy PDO connection. These use the existing `custom` span type and the query recorder's tracing and error-reporting settings. Read, write, and direct connections (where supported by Laravel) are distinguished by `laravel.db.connection.role`. Existing connector closures are preserved; recording does not open connections early or execute SQL.

Connection spans contain only the driver, Laravel connection name, and connection role. Failed attempts close with an error status and rethrow the original exception without adding its message or connection configuration to the span.

This relies on Laravel's `ConnectionEstablished` event, which announces the Laravel connection wrapper, not an open PDO connection. Laravel 10.14 does not emit this event. Laravel 11.47 and 12.42 emit it for new wrappers, but not reconnects; reconnect tracing requires Laravel 12.43+ or 13. Connections created before the recorder boots, already-open PDO instances, and connections in active transactions are left untouched. Direct connections are wrapped only when their raw getter and setter are available.

Query durations remain exactly those reported by Laravel, including lazy connection setup in the first query. Query spans are reconstructed after execution, so connection spans share the surrounding parent rather than being nested under that query. Their durations can overlap and should not be added together. Redis connections are not covered.

## Support us

[<img src="https://spatie.be/github-ad-image/laravel-flare.jpg" width="419px" />](https://spatie.be/github-ad-click/laravel-flare)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

The test suite includes integration tests that require a running workbench server. To run the full test suite locally:

```bash
# Install dependencies
composer install

# Build the workbench application
composer run build

# Start the workbench server and queue worker in the background
vendor/bin/testbench serve &
vendor/bin/testbench queue:work &

# Wait for the workbench/storage symlink to be created, then run tests
vendor/bin/pest
```

To run only the unit tests (without the workbench server):

```bash
vendor/bin/pest --exclude-group=integration
```

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Spatie](https://spatie.be)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

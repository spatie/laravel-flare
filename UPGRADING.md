# Upgrading

Because there are many breaking changes an upgrade is not that easy. There are many edge cases this guide does not
cover. We accept PRs to improve this guide.

## From spatie/laravel-ignition

We created `spatie/laravel-flare` to make it easier to use Flare in Laravel without adding Ignition our custom build
error page. This package can only be installed on Laravel 11.10 and up.

When upgrading, please merge the contents of the `ignition.php` config file into the `flare.php` config file. And check
if new config options are available in the `flare.php` config file.

That's it!

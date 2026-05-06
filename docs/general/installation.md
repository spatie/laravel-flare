---
title: Installation 
---

Use the installation guide below to match your Laravel version, app structure, and optional features like performance monitoring or Vapor:

Performance monitoring is available for Laravel 11 and newer.

<div>
<flare-installation-guide technology="Laravel"></flare-installation-guide>
</div>

Great! From now on, Flare will track all errors and exceptions throughout your application.

## Using an older Laravel/PHP version or the old application structure?

In the past we've had multiple clients without support for performance monitoring. While these packages are still available, we recommend using the newer packages for all new projects:

- [spatie/laravel-flare v1](/docs/laravel/older-packages/laravel-flare-v1): supports PHP 8.1 and later, Laravel 11.0 and 12.0
- [spatie/laravel-ignition](https://github.com/spatie/laravel-ignition): supports PHP 7.1 and later, Laravel 5.5 until 12.0
- [spatie/flare-client-php v1](/docs/php/older-packages/flare-client-php-v1): supports PHP 8.0 and later
- [facade/flare-client-php v1](https://github.com/facade/flare-client-php): supports PHP 7.1 until 8.0

## Using Ignition?

The current Flare client and Laravel Flare package are incompatible with Ignition. If you want to use Flare, you need to remove Ignition from your project.

Due to high demand, we are working on a new version of the Flare client that will be compatible with Ignition. We will keep you updated on our progress.

## Upgrading

You can find more information about the `laravel-flare` upgrade from v1 to v2 [here](https://github.com/spatie/laravel-flare/blob/main/UPGRADING.md).

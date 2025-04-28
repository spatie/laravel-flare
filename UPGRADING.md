# Upgrading

Because there are many breaking changes an upgrade is not that easy. There are many edge cases this guide does not
cover. We accept PRs to improve this guide.

## From v1 to v2

- In the previous version when anonymising user Ip's a middleware had to be removed, in this version you can set the `censor.client_ips` option to `true` in the config file.
- The `CensorRequestBodyFields` middleware was removed. You can now use the `censor.body_fields` option in the config file to specify which fields should be censored.
- The `CensorRequestHeaders` middleware was removed. You can now use the `censor.headers` option in the config file to specify which headers should be censored.
- In the previous version Flare would send a whole copy of the user model when logged in. In this version only an id, email, name and some attributes (if the `toFlare` method is implemented on the user) of the user will be sent. This can be configured by creating your own `UserAttributesProvider`

## From spatie/laravel-ignition

We created `spatie/laravel-flare` to make it easier to use Flare in Laravel without adding Ignition our custom build
error page. This package can only be installed on Laravel 11.10 and up.

When upgrading, please merge the contents of the `ignition.php` config file into the `flare.php` config file. And check
if new config options are available in the `flare.php` config file.

That's it!

# Upgrading

Because there are many breaking changes an upgrade is not that easy. There are many edge cases this guide does not
cover. We accept PRs to improve this guide.

## From v2 to v3


### Update your config file

- `send_logs_as_events` - this key no longer exists. Log shipping is now a first-class feature controlled by the `log` key (see below).
- A `report` key has been added to enable/disable error reporting, it is enabled by default.
- A `trace` key has been added to enable/disable tracing, it is enabled by default.
- A `log` key has been added to enable/disable the new standalone log shipping feature, it is disabled by default.

### Logging setup changed

Flare now has a dedicated logging system available, you enable it as such:

1. **Add** `'log' => true` to your `config/flare.php` to enable the new standalone log shipping feature.
2. **Route logs to flare**  add the `flare` channel in your `config/logging.php`:
    ```php
    'channels' => [
       'flare' => [
           'driver' => 'flare',
       ],
    ],
    ```
    And add it to your log stack in `.env`:
    ```
    LOG_STACK=single,flare
    ```
   
### The base Flare client package

Was also completely rewritten, we recommend you to also check the [upgrade guide](https://github.com/spatie/flare-client-php/blob/main/UPGRADING.md) for that package.

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

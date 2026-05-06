---
title: Ignition
---

**This is an older version of the package, and we recommend that you upgrade to the latest version of the Flare client.**

## Installation


On this page, you'll learn how to install Ignition in your project

### Laravel apps

In Laravel applications, Ignition is installed by default.

Optionally, you could publish the `ignition` and `flare` config file to have fine-grained control over how Ignition behaves.

```bash
php artisan vendor:publish --tag="ignition-config"
php artisan vendor:publish --tag="flare-config"
```

@## Non-Laravel apps

In a non-Laravel PHP application, you can install Ignition via Composer.

```bash
composer require spatie/ignition
```

Here's a minimal example on how to register ignition. 

```php
use Spatie\Ignition\Ignition;

include 'vendor/autoload.php';

Ignition::make()->register();
```

In order to display the Ignition error page when an error occurs in your project, you must add this code. Typically, this would be done in the bootstrap part of your application.

```php
Spatie\Ignition\Ignition::make()->register();
```

#### Setting the application path

When setting the application path, Ignition will trim the given value from all paths. This will make the error page look
more cleaner.

```php
Spatie\Ignition\Ignition::make()
    ->applicationPath($basePathOfYourApplication)
    ->register();
```

#### Using dark mode

By default, Ignition uses a nice white based theme. If this is too bright for your eyes, you can use dark mode.

```php
Spatie\Ignition\Ignition::make()
    ->useDarkMode()
    ->register();
```

### Stack trace arguments

By default, Ignition will show you arguments which are passed to functions and methods in the stack trace. You can find more about how this works in the [stack trace arguments](/docs/integration/generic-php-projects/stacktrace-arguments) section.

### Avoid rendering Ignition in a production environment

You don't want to render the Ignition error page in a production environment, as it potentially can display sensitive
information.

To avoid rendering Ignition, you can call `shouldDisplayException` and pass it a falsy value.

```php
Spatie\Ignition\Ignition::make()
    ->shouldDisplayException($inLocalEnvironment)
    ->register();
```

## Reporting security issues

Please don't use the public issue tracker, but report all security issues to [info@spatie.be](mailto:info@spatie.be)

## Sharing errors

### Introduction

The error sharing feature in Ignition enables users to easily share their local error occurrences with colleagues. By clicking the "share" button, users can generate a unique and publicly accessible URL that displays their local error. Ignition shares are ideal for attaching to GitHub issues or sharing in Slack because they have no expiration date. The sharing capability is provided by [Flare](https://flareapp.io/) at no cost and without any usage restrictions.

### What data gets shared?

When using Ignition's sharing feature, you can choose what exception data and context to include in the shared error page. The following three sections are available for sharing and correspond to Ignition's page sections:

1. **Stack**: Provides a detailed stack trace that shows the sequence of method calls leading to the error, including file paths and arguments.
2. **Context**: Offers relevant contextual information about the error, such as request payload, headers, routing details, and view variables.
3. **Debug**: Displays additional debugging information like `dump` output, SQL queries with bindings, and logs, providing deeper insights into the error.

_Generally, the data included in the Ignition error page and its shared errors is safe to share with colleagues. However, as always, common sense applies. It is important to note that the data may contain sensitive information, such as database credentials, API keys, or other secrets. Therefore, it is recommended to review the data visible in Ignition before sharing it with others. Be especially careful when dealing with production data locally._ 

### Removing shared errors

To maintain privacy and control over shared error pages, Flare offers a simple method for removing shares. When sharing an error, an ownership cookie is automatically set in your browser. To delete the shared error, simply visit the shared error from the same browser and look for the "Delete Share" button. 

In the rare event that the "Delete Share" button is not visible or you encounter any issues, please contact Flare's dedicated support team at [support@flareapp.io](mailto:support@flareapp.io) and include the share URL you would like to have removed.

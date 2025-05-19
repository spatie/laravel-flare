# Laravel Flare: send Laravel errors to Flare

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-flare.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-flare)
![Tests](https://github.com/spatie/laravel-flare/workflows/Run%20tests/badge.svg)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-flare.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-flare)

[Laravel Flare](https://flareapp.io/docs/integration/laravel-customizations/introduction) allows to publicly share your errors on [Flare](https://flareapp.io). If configured with a valid Flare API key, your errors in production applications will be tracked, and you'll get notified when they happen.

`spatie/laravel-flare` works for Laravel 11 and above applications running on PHP 8.2 and above. Looking for Ignition for Laravel, the most beautiful error page out there? You can still install `spatie/laravel-ignition` and admire it.

## Introduction

When creating a new project on Flare, we'll display installation instructions for your app. Even though the default settings will work fine for all projects, we offer some customization options that you might like.

The easiest way to install Flare is by using the `spatie/laravel-flare` package. It allows you to send error to Flare.

Alternatively, you could opt to install `spatie/laravel-ignition` which does exactly the same as `spatie/laravel-flare`. Additionally, it will display a custom error page, called Ignition, in Laravel applications. This error page has a lot of extra features compared to Laravel's default error page.

The `spatie/laravel-flare` package is only available for Laravel 11 and above. If you're using Laravel 10 or below, you'll need to use the `spatie/laravel-ignition` package.

## Security

Ignition has the ability to [run executable solutions](https://flareapp.io/docs/solutions/making-solutions-runnable). These solutions can make your life better by running migrations when you forgot to run them, generating an `APP_KEY` if you set none, fixing variable names in your code, ...

These runnable solutions are only available when Laravel is in [debug mode](https://laravel.com/docs/8.x/configuration#debug-mode).

**We highly recommend to never turn on debug mode on a non-local environment. If you do so then you risk exposing sensitive information and potentially allow outsiders to execute solutions.**

Should you have activated debug mode on a non-local environment, then Ignition will display a warning.

### Disable executing solutions

Should you, for some reason, do need to set debug mode to `true` on a non-local environment, then we highly recommend turning off Ignition's ability to execute solutions. You can do this by setting the `ignition.enable_runnable_solutions` config key to `false`.

If you're using Ignition v2.6.1 or higher, then it's not possible anymore to run solutions in a non-local environment.

## Controlling collected data

You have full control over what data should get collected and sent to Flare.

Flare middleware will add information to the report that is sent to Flare. You can disable and configure middlewares in the `flare` config file.

### Anonymizing IPs

By default, Flare does not collect and send information about the IP address of your application users. If you want to collect this information, you can remove the `RemoveRequestIp` middleware.

### Censoring request body/header fields

When an exception occurs in a web request, the Flare client will pass on request fields that are present in the body. By default, Flare will replace the value any fields that are named "password" with "<CENSORED>".

To censor out values of additional fields, you put the names of those fields in the config of the `CensorRequestBodyFields` middleware:

```php
// config/flare.php

return [
    // ...
    
    'flare_middleware' => [
        CensorRequestBodyFields::class => [
            'censor_fields' => [
                'password',
                'password_confirmation',
                'other_field',
            ],
        ],
    
        // ...
    ],   
]
```

It is also possible to censor out values of request headers. You can do this by adding the `headers` key to the `CensorRequestHeaders` middleware:

```php
// config/flare.php

return [
    // ...
    
    'flare_middleware' => [
        CensorRequestHeaders::class => [
            'headers' => [
                'Authorization',
                'Other-Header',
            ],
        ],
    
        // ...
    ],   
]
```

### Git information

By default, Flare collects the current commit hash, the commit message as well as the repository URL so that you can easily link an exception with the commit hash that was checked out on your deployed application.

If you wish to disable this information, you can remove the `AddGitInformation` middleware.

### Environment information

Flare collects information about your environment, such as the PHP version, the Laravel version  and the server information. If you wish to disable this information, you can remove the `AddEnvironmentInformation` middleware.

### Dumps

Flare automatically collects all of your executed dumps that happened before the exception occurred. If you want to disable this, you can remove the `AddDumps` middleware.

### Logs

Flare automatically collects all of your logs that happened before the exception occurred. If you want to disable this, you can remove the `AddLogs` middleware.

### Jobs

Flare logs executed jobs and their payload. If you want to disable this, you can remove the `AddJobs` middleware.

### SQL Queries

Flare automatically collects all of your executed queries that happened before the exception occurred. If you want to disable this, you can remove the `AddQueries` middleware.

The bindings of the queries are also collected by default. If you want to disable this, you can set the `report_query_bindings` key to `false`.

### Users

When a user is logged in to your Laravel application and an error occurs then a copy of the user model is sent to Flare.

You can hide certain properties by adding them to the [$hidden](https://laravel.com/docs/8.x/eloquent-serialization#hiding-attributes-from-json) property of your model or by implementing a `toFlare` method on the model:

```php
class User extends BaseUser
{
    public function toFlare()
    {
        return [
            'name' => $this->name,
            'email' => $this->email
        ];
    }
}
```

## Ignoring errors

By default, all exceptions will be reported to Flare. You can change this behaviour by filtering the exceptions with a callable:

```php
use \Spatie\LaravelIgnition\Facades\Flare;

Flare::filterExceptionsUsing(
    fn(Throwable $throwable) =>  !$throwable instanceof AuthorizationException
);
```

Additionally, you can provide a callable to the `Flare::filterReportsUsing` method to stop a report from being sent to Flare. Compared to `filterExceptionsCallable`, this can also prevent logs and errors from being sent.

```php
Flare::filterReportsUsing(function(Report $report)  {
    // return a boolean to control whether the report should be sent to Flare
    return true;
});
```

Finally, it is also possible to set the levels of errors reported to Flare as such:

```php
Flare::reportErrorLevels(E_ALL & ~E_NOTICE); // Will send all errors except E_NOTICE errors
```

## Linking to errors

When an error occurs in a web request, Laravel will show this error page by default.

![screenshot](/images/blog/linking/default.png)

If a user sees this page and wants to report this error to you, the user usually only reports the URL and the time the error was seen.

To let your users pinpoint the exact error they saw, you can display the UUID of the error sent to Flare.

If you haven't already done so, you [can publish Laravel's default error pages](https://laravel.com/docs/8.x/errors#custom-http-error-pages) with this command.

```bash
php artisan vendor:publish --tag=laravel-errors
```

Typically, you would alter the `resources/views/errors/500.blade.php` to display the UUID and optionally a URL of the latest error sent to Flare.

```blade
@verbatim
@extends('errors::minimal')

@section('title', __('Server Error'))
@section('code', '500')
@section('message')
    Server error
+    <div>
+        <a href="{{ Flare::sentReports()->latestUrl() }}">
+            {{ Flare::sentReports()->latestUuid() }}
+        </a>
+    </div>
@endsection
@endverbatim
```

This is how that would look like in the browser.

![screenshot](/images/blog/linking/uuid.png)

That link returned by `Flare::sentReports()->latestUrl()` isn't publicly accessible, the page is only visible to Flare users that have access to the project on Flare.

In certain cases, multiple errors can be reported to Flare in a single request. To get a hold of the UUIDs of all sent errors, you can call `Flare::sentReports()->uuids()`. You can get links to all sent errors with `Flare::sentReports()->urls()`.

It is possible to search for certain errors in Flare using the UUID, you can find more information about that [here](/docs/flare/general/searching-errors).

## Identifying users

When reporting an error to Flare, we'll automatically send along the properties of the authenticated user. Behind the scenes, we'll call `toArray` on your `User` model. This will exclude all attributes that are marked as hidden in your model, so we're not sending along the password.

If you need more control over which user data you want to send to Flare, you can customize this by adding a `toFlare` method to your `User` model. If we detect that your model has a `toFlare` method we'll use the returned array as the user information instead of `toArray`.

```php
class User extends Model {
    //

   public function toFlare(): array {
      // Only `id` will be sent to Flare.
      return [
         'id' => $this->id
      ];
   }
}
```

## Adding custom context

When you send an error to Flare, we already collect a lot of Laravel and user specific information for you and send it along with the exceptions that happened in your application.
But you can also add custom context to your application. This can be very useful if you want to provide key-value related information that furthermore helps you to debug a possible exception.

For example, your application could be in a multi-tenant environment and in addition to reporting the user, you also want to provide a key that quickly lets you identify which tenant was active when the exception occurs.

Flare allows you to set custom context items using the like this:

```php
use Spatie\LaravelFlare\Facades\Flare; // Replace by Spatie\LaravelIgnition\Facades\Flare when using the `spatie/laravel-ignition` package

Flare::context('Tenant', 'My-Tenant-Identifier');
```

This could for example be set automatically in a Laravel service provider or an event. So the next time an exception happens, this value will be sent along to Flare and you can find it on the "Context" tab.

### Grouping multiple context items

Sometimes you may want to group your context items by a key that you provide to have an easier visual differentiation when you look at your custom context items.

The Flare client allows you to also provide your own custom context groups like this:

```php
use Spatie\LaravelFlare\Facades\Flare; // Replace by Spatie\LaravelIgnition\Facades\Flare when using the `spatie/laravel-ignition` package

Flare::group('Custom information', [
    'key' => 'value',
    'another key' => 'another value',
]);
```

## Adding glows

In addition to [custom context items](/docs/integration/laravel-customizations/adding-custom-context), you can also add "Glows" to your application.
Glows allow you to add little pieces of information, that can later be found in a chronological order in the "Debug" tab of your application.

You can think of glows as breadcrumbs that can help you track down which parts of your code an exception went through.

To add a glow to your application, you can do this:

```php
use Spatie\LaravelFlare\Facades\Flare; // Replace by Spatie\LaravelIgnition\Facades\Flare when using the `spatie/laravel-ignition` package
use Spatie\FlareClient\Enums\MessageLevels;

Flare::glow('This is a message from glow!', MessageLevels::DEBUG, func_get_args());
```

## Setting a version number

Optionally, you can configure Flare to add a version number to all sent exceptions. Typically, this is done in a service provider.

```php
use Spatie\LaravelFlare\Facades\Flare; // Replace by Spatie\LaravelIgnition\Facades\Flare when using the `spatie/laravel-ignition` package

Flare::determineVersionUsing(function() {
   return '1.0' ; // return your version number
});
```

When you're looking at an error in Flare, we'll display the version number across our UI.

## Stacktrace arguments

When an error occurs in your application, Flare will send the stacktrace of the error to Flare. This stacktrace contains the file and line number where the error occurred and the argument values passed to the function or method that caused the error.

These argument values have been significantly reduced to make them easier to read and reduce the amount of data sent to Flare, which means that the arguments are not always complete. To see the full arguments, you can always use a [glow](/docs/integration/laravel-customizations/adding-glows) to send the whole parameter to Flare.

For example, let's say you have the following Carbon object:

```php
new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels'))
```

Flare will automatically reduce this to the following:

```
16 May 2020 14:00:00 +02:00
```

It is possible to configure how these arguments are reduced. You can even implement your own reducers!

By default, the following reducers are used:

- BaseTypeArgumentReducer
- ArrayArgumentReducer
- StdClassArgumentReducer
- EnumArgumentReducer
- ClosureArgumentReducer
- DateTimeArgumentReducer
- DateTimeZoneArgumentReducer
- SymphonyRequestArgumentReducer
- ModelArgumentReducer
- CollectionArgumentReducer
- StringableArgumentReducer

### Implementing your reducer

Each reducer implements `Spatie\FlareClient\Arguments\Reducers\ArgumentReducer`. This interface contains a single method, `execute` which provides the original argument value:

```php
interface ArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract;
}
```

In the end, three types of values can be returned:

When the reducer could not reduce this type of argument value:

```php
return UnReducedArgument::create();
```

When the reducer could reduce the argument value, but a part was truncated due to the size:

```php
return new TruncatedReducedArgument(
    array_slice($argument, 0, 25), // The reduced value
    'array' // The original type of the argument
);
```

When the reducer could reduce the full argument value:

```php
return new TruncatedReducedArgument(
    $argument, // The reduced value
    'array' // The original type of the argument
);
```

For example, the `DateTimeArgumentReducer` from the example above looks like this:

```php
class DateTimeArgumentReducer implements ArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract
    {
        if (! $argument instanceof \DateTimeInterface) {
            return UnReducedArgument::create();
        }
        
        return new ReducedArgument(
            $argument->format('d M Y H:i:s p'),
            get_class($argument),
        );
    }
}
```

### Configuring the reducers

Reducers can be added to the `flare.php` config file (or `ignition.php` if you're using the `spatie/laravel-ignition` package):

```php
/*
    |--------------------------------------------------------------------------
    | Argument reducers
    |--------------------------------------------------------------------------
    |
    | Ignition show you stack traces of exceptions with the arguments that were
    | passed to each method. To make these variables more readable, you can
    | specify a list of classes here which summarize the variables.
    |
    */
    'argument_reducers' => [
        \Spatie\Backtrace\Arguments\Reducers\BaseTypeArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\ArrayArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\StdClassArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\EnumArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\ClosureArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\DateTimeArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\DateTimeZoneArgumentReducer::class,
        \Spatie\Backtrace\Arguments\Reducers\SymphonyRequestArgumentReducer::class,
        \Spatie\LaravelFlare\ArgumentReducers\ModelArgumentReducer::class, // Or \Spatie\LaravelIgnition\ArgumentReducers\ModelArgumentReducer::class when using the `spatie/laravel-ignition` package
        \Spatie\LaravelFlare\ArgumentReducers\CollectionArgumentReducer::class, // Or \Spatie\LaravelIgnition\ArgumentReducers\CollectionArgumentReducer::class when using the `spatie/laravel-ignition` package
        \Spatie\Backtrace\Arguments\Reducers\StringableArgumentReducer::class,
    ],
```

Reducers are executed from top to bottom. The first reducer which doesn't return an `UnReducedArgument` will be used.

### Disabling stack frame arguments

If you don't want to send any arguments to Flare, you can turn off this behavior within the `flare.php` config file (or `ignition.php` if you're using the `spatie/laravel-ignition` package):

```php
    /*
    |--------------------------------------------------------------------------
    | Include arguments
    |--------------------------------------------------------------------------
    |
    | Ignition show you stack traces of exceptions with the arguments that were
    | passed to each method. This feature can be disabled here.
    |
    */

    'with_stack_frame_arguments' => false,
```

### Missing arguments?

- Make sure you've got the latest version of Flare / Ignition
- Check that `with_stack_frame_arguments` is not disabled
- Check your ini file whether `zend.exception_ignore_args` is enabled, it should be `0`

## Handling exceptions

When an exception is thrown in an application, the application stops executing and the exception is reported to Flare.
However, there are cases where you might want to handle the exception so that the application can continue running. And
the user isn't presented with an error message.

In such cases it might still be useful to report the exception to Flare, so you'll have a correct overview of what's
going on within your application. We call such exceptions "handled exceptions".

Within Laravel, it is possible to handle an exception by catching it and then reporting it:

```php
try {
    // Code that might throw an exception
} catch (Exception $exception) {
    report($exception);
}
```

In Flare, we'll show that the exception was handled, it is possible to filter these exceptions. You'll find more about filtering exceptions [here](/docs/flare/general/searching-errors).

### Laravel Octane

Flare works out of the box with Laravel Octane. No further configuration is required!

## Laravel Horizon

It is possible to connect a Flare project with your Laravel Horizon installation. This way, you can quickly jump from an
exception to the corresponding job in Horizon and retry it.

### Configuration

Within your project settings, open up the Laravel page:

![screenshot](/images/docs/laravel-horizon-1.png)

Provide the URL to your Laravel Horizon dashboard(most of the time this looks
like `https://your-app-domain.com/horizon`) and click save.

### Usage

When viewing an exception in Flare triggered by a Horizon Job, you can now easily jump to the corresponding job in
Horizon:

![screenshot](/images/docs/laravel-horizon-2.png)

Please notice, by default Laravel Horizon will keep the failed job information for seven days, failed jobs older than
seven days will not be available in Horizon anymore. You can adjust this setting in your `horizon.php` configuration
by updating the `trim` option:

```php
'trim' => [
    'recent' => 60,
    'pending' => 60,
    'completed' => 60,
    'recent_failed' => 43200, // 30 days
    'failed' => 43200, // 30 days
    'monitored' => 10080,
],
```

## Writing custom middleware

Before Flare receives the data that was collected from your local exception, we give you the ability to call custom middleware methods.
These methods retrieve the report that should be sent to Flare and allow you to add custom information to that report.

Just like with the Flare client itself, you can [add custom context information](/docs/integration/laravel-customizations/adding-custom-context) to your report as well. This allows you to structure your code so that you have all context related changes in one place.

You can register a custom middleware by using the `registerMiddleware` method on the `Facade\FlareClient\Flare` class, like this:

```php
use Spatie\FlareClient\Report;
use Spatie\LaravelFlare\Facades\Flare; // Replace by Spatie\LaravelIgnition\Facades\Flare when using the `spatie/laravel-ignition` package

Flare::registerMiddleware(function (Report $report, $next) {
    // Add custom information to the report
    $report->context('key', 'value');

    return $next($report);
});
```

A middleware can either be a callable, as seen above, or a custom class that implements a `handle` method. This class can make use of dependency injection in its constructor:

Here is an example:

```php
use Spatie\LaravelFlare\Facades\Flare; // Replace by Spatie\LaravelIgnition\Facades\Flare when using the `spatie/laravel-ignition` package

Flare::registerMiddleware(FlareMiddleware::class);
``` 

To create a middleware that, for example, removes all the session data before your report gets sent to Flare, the middleware implementation might look like this:

```php
use Spatie\FlareClient\Report;

class FlareMiddleware
{
    public function handle(Report $report, $next)
    {
	    $context = $report->allContext();

	    $context['session'] = null;

	    $report->userProvidedContext($context);

	    return $next($report);
    }
}
```

##  Customizing error grouping

Flare has a [special grouping](docs/flare/general/error-grouping) algorithm that groups similar error occurrences into errors to make understanding what's going on in your application easier.

While the default grouping algorithm works for 99% of the cases, there are some cases where you might want to customize the grouping.

This can be done on an exception class base, you can tell Flare to group all exceptions of a specific class together by setting the following in the `flare.php` config file:

```php
use Spatie\FlareClient\Enums\OverriddenGrouping;

// flare.php config file

'overridden_groupings' => [
    SomeExceptionClass::class => OverriddenGrouping::ExeptionClass,
],
```

In this case every exception of the `SomeExceptionClass` will be grouped together no matter what the message or stack trace is.

It is also possible to group exceptions of the same class together, but also take the message into account:

```php
use Spatie\FlareClient\Enums\OverriddenGrouping;

// flare.php config file

'overridden_groupings' => [
    SomeExceptionClass::class => OverriddenGrouping::ExceptionMessageAndClass,
],
```

Be careful when grouping by class and message, since every occurrence might have a slightly different message, this could lead to a lot of different errors.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-flare.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-flare)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Spatie](https://spatie.be)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

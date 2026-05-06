---
title: Linking to errors 
---


Laravel will show this error page by default when an error occurs in a web request.

![screenshot](/images/docs/linking/default.png)

If a user sees this page and wants to report this error to you, the user usually only reports the URL and the time the error was seen.

You can display the UUID of the error sent to Flare to help your users pinpoint the error they saw.

If you haven't already done so, you can publish Laravel's default error pages (https://laravel.com/docs/12.x/errors#custom-http-error-pages) with this command.

```bash
php artisan vendor:publish --tag=laravel-errors
```

Typically, you would alter the `resources/views/errors/500.blade.php` to display the UUID and, optionally, a URL of the latest error sent to Flare.

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

This is how it would look in the browser.

![screenshot](/images/docs/linking/uuid.png)

The link returned by `Flare::sentReports()->latestUrl()` isn't publicly accessible; the page is only visible to Flare users who have access to the project on Flare.

Sometimes, multiple errors can be reported to Flare in a single request. To get a hold of the UUIDs of all sent errors, you can call `Flare::sentReports()->uuids()`. You can get links to all sent errors with `Flare::sentReports()->urls()`.

It is possible to search for specific errors in Flare using the UUID; you can find more information about that [here](/docs/flare/errors/searching-errors). 

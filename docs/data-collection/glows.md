---
title: Glows 
---


Glows allow you to add little pieces of information that can later be found in chronological order in the "Debug" tab of your application when you debug an error or as events on a span when viewing a trace in performance monitoring.

Glows are like breadcrumbs that help you track down which parts of your code were executed.

You can add a glow to your application like this:

```php
use Spatie\LaravelFlare\Facades\Flare; // Replace by
use Spatie\FlareClient\Enums\MessageLevels;

Flare::glow()->record('This is a message from glow!', MessageLevels::Debug, func_get_args());
``` 
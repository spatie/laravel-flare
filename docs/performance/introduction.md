---
title: Introduction 
---


Flare allows you to run performance monitoring on your application. This means you can see how long certain parts of your code take to execute.

To do this, we'll follow the Open Telemetry tracing standard. Let's give you a quick introduction:

> An application always starts a **trace** for every request, command or job. A sampler decides whether a trace will be **sampled** based on the sampling rate and a randomiser. When a trace is sampled, spans will be recorded for the trace. A **span** is a work unit executed within a trace. A span can have a parent span, which means that the span is executed within the timeframe of another span. A span always has a start and end time. Whenever a time-based event happens within a span (with only a single timestamp and no specific start or end time), then we'll call such an event a **span event**. Sometimes a trace is distributed over multiple machines/services, think a request triggering a queued job. In such a case, the application sends the trace_id, span_id and sampling decision to the next server/service to continue the trace, we call this **propagation**.

That's it, you're now a tracing expert!

## Enabling performance monitoring

To enable performance monitoring, you need to set the `trace` option to `true` in the `flare.php` config file:

```php
'trace' => true,
```

## Starting traces

Flare's Laravel client will automatically start traces when a request, command, or job is processed by a Laravel application. Thus, setting this up requires no manual work.

## Collecting spans & span events

The Flare Laravel client will automatically collect all sorts of events within your Laravel application, like:

- Queries
- Requests
- Commands
- Jobs
- View
- External HTTP Requests
- Logs
- and so much more ...

In the next chapter, you can read about how we collect data, how to configure data collection and add your own data collectors.

## Disabling performance monitoring

Sometimes you might not want to enable performance monitoring for your application. This can be done in the `flare.php` config file:

```php
'trace' => false,
``` 

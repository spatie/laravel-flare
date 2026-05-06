---
title: Limits 
---


When tracing an application, the amount of data can grow very large. We have a couple of limits in place to limit the amount of data sent to Flare.

- max spans per trace: 512
- max span events per span: 128
- max attributes per span: 128
- max attributes per span event: 128

It is possible to lower or raise these limits as such in the `flare.php` config file:

```php
'trace_limits' => [
    'max_spans' => 512,
    'max_attributes_per_span' => 128,
    'max_span_events_per_span' => 128,
    'max_attributes_per_span_event' => 128,
],
```
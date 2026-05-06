---
title: Livewire
---


When an error occurs in a Livewire component, Flare will automatically collect:

- The component class
- The updates made in the request
- The data present in the component

This data will be collected for all the components in the current Livewire request.

When using performance monitoring, Flare kan keep track of the components being rendered by your application, by default we'll collect the phases a component goes through:

- mounting / hydrating
- calls to the component
- rendering
- dehydrating

It is possible to combine all these phases into one single span like this:

```php
use Spatie\LaravelFlare\Enums\LaravelCollectType;

FlareConfig::defaultCollects(
    extra: [
        LaravelCollectType::LivewireComponents->value => [
            'split_by_phase' => false,
        ],
    ],
]);
```

When you want to ignore a specific livewire component from being collected, you can do so by adding the component class to the `ignore` array:

```php
FlareConfig::defaultCollects(
    ignore: [
        LaravelCollectType::LivewireComponents->value => [
            'ignore' => \App\Http\Livewire\SomeComponent::class,
        ],
    ],
]);
```

The Livewire functionality is enabled by default, but you can disable it by ignoring the `Livewire` collect in `config.php`:

```php
'collects' => FlareConfig::defaultCollects(
    ignore: [LaravelCollectType::LivewireComponents],
),
```

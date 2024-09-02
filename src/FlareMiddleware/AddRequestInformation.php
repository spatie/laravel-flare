<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Illuminate\Http\Request as LaravelRequest;
use Spatie\FlareClient\FlareMiddleware\AddRequestInformation as BaseAddRequestInformation;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;

/**
 * @property LaravelRequestAttributesProvider $attributesProvider
 */
class AddRequestInformation extends BaseAddRequestInformation
{
    protected bool $includeLivewireComponents = false;

    public function __construct(
        LaravelRequestAttributesProvider $attributesProvider,
        $config
    ) {
        $this->includeLivewireComponents = $config['include_livewire_components'] ?? false;

        parent::__construct($attributesProvider, $config);
    }

    protected function isRunningInConsole(): bool
    {
        return app()->runningInConsole();
    }

    protected function getAttributes(): array
    {
        $request = app(LaravelRequest::class);

        return $this->attributesProvider->toArray($request, $this->includeLivewireComponents);
    }
}

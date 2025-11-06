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

    protected array $ignoreLivewireComponents = [];

    public function __construct(
        LaravelRequestAttributesProvider $attributesProvider,
        array $config
    ) {
        $this->includeLivewireComponents = $config['include_livewire_components'] ?? false;
        $this->ignoreLivewireComponents = $config['ignore_livewire_components'] ?? [];

        parent::__construct($attributesProvider);
    }

    protected function isRunningInConsole(): bool
    {
        return app()->runningInConsole();
    }

    protected function getAttributes(): array
    {
        $request = app(LaravelRequest::class);

        return $this->attributesProvider->toArray(
            $request,
            includeContents: true,
            includeLivewireComponents: $this->includeLivewireComponents,
            ignoreLivewireComponents: $this->ignoreLivewireComponents
        );
    }
}

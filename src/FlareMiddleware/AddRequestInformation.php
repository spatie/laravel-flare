<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Illuminate\Http\Request as LaravelRequest;
use Spatie\FlareClient\FlareMiddleware\AddRequestInformation as BaseAddRequestInformation;
use Spatie\FlareClient\Support\Redactor;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Spatie\LaravelFlare\AttributesProviders\LaravelUserAttributesProvider;
use Throwable;

class AddRequestInformation extends BaseAddRequestInformation
{
    protected bool $includeLivewireComponents = false;

    /** @var array<string> */
    protected array $ignoreLivewireComponents = [];

    public function __construct(
        Redactor $redactor,
        protected LaravelRequest $request,
        array $config = [],
    ) {
        $this->includeLivewireComponents = $config['include_livewire_components'] ?? false;
        $this->ignoreLivewireComponents = $config['ignore_livewire_components'] ?? [];

        parent::__construct($redactor);
    }

    protected function isRunningInConsole(): bool
    {
        return app()->runningInConsole();
    }

    protected function getAttributes(): array
    {
        $attributes = (new LaravelRequestAttributesProvider(
            $this->redactor,
            $this->request,
            includeContents: true,
            includeLivewireComponents: $this->includeLivewireComponents,
            ignoreLivewireComponents: $this->ignoreLivewireComponents,
        ))->toArray();

        try {
            $user = $request->user();
        } catch (Throwable) {
            $user = null;
        }

        if (is_object($user)) {
            $attributes = [
                ...$attributes,
                ...(new LaravelUserAttributesProvider($user))->toArray(),
            ];
        }

        return $attributes;
    }
}

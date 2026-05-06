<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Illuminate\Http\Request as LaravelRequest;
use Spatie\FlareClient\AttributesProviders\SymfonyRequestAttributesProvider;
use Spatie\FlareClient\Support\Redactor;
use Spatie\LaravelFlare\Support\LivewireComponentFinder;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class LaravelRequestAttributesProvider extends SymfonyRequestAttributesProvider
{
    /** @param array<string> $ignoreLivewireComponents */
    public function __construct(
        Redactor $redactor,
        protected LivewireComponentFinder $livewireComponentFinder,
        ?Request $request = null,
        bool $includeContents = true,
        protected bool $includeLivewireComponents = false,
        protected array $ignoreLivewireComponents = [],
    ) {
        parent::__construct($redactor, $request, $includeContents);
    }

    public function toArray(): array
    {
        $attributes = parent::toArray();

        if (! $this->request instanceof LaravelRequest) {
            return $attributes;
        }

        if (! $this->includeLivewireComponents || ! $this->isRunningLiveWire($this->request)) {
            return $attributes;
        }

        try {
            $livewireAttributesProvider = new LivewireAttributesProvider(
                livewireComponentFinder: $this->livewireComponentFinder,
                request: $this->request,
                ignoreLivewireComponents: $this->ignoreLivewireComponents,
            );

            return [
                ...$attributes,
                ...$livewireAttributesProvider->toArray(),
            ];
        } catch (Throwable) {
            return $attributes;
        }
    }

    protected function isRunningLiveWire(LaravelRequest $request): bool
    {
        return $request->hasHeader('x-livewire') && $request->hasHeader('referer');
    }
}

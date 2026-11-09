<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Illuminate\Http\Request as LaravelRequest;
use Spatie\FlareClient\AttributesProviders\SymfonyRequestAttributesProvider;
use Spatie\FlareClient\Support\Redactor;
use Spatie\LaravelFlare\Support\LivewireComponentFinder;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
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
                redactor: $this->redactor,
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

    /**
     * Livewire sends the state of each component as a JSON string in `components.*.snapshot`.
     * The redactor cannot look inside a string, so the snapshots are decoded here and the
     * regular body censoring then reaches the component data.
     */
    protected function getInputBag(): InputBag|ParameterBag
    {
        $bag = parent::getInputBag();

        if (! $this->request instanceof LaravelRequest) {
            return $bag;
        }

        if (! $this->isRunningLiveWire($this->request)) {
            return $bag;
        }

        $input = $bag->all();

        if (! is_array($input['components'] ?? null)) {
            return $bag;
        }

        foreach ($input['components'] as $index => $component) {
            if (! is_array($component)) {
                continue;
            }

            if (! is_string($component['snapshot'] ?? null)) {
                continue;
            }

            $snapshot = json_decode($component['snapshot'], true);

            if (! is_array($snapshot)) {
                continue;
            }

            $input['components'][$index]['snapshot'] = $snapshot;
        }

        return new InputBag($input);
    }

    protected function isRunningLiveWire(LaravelRequest $request): bool
    {
        return $request->hasHeader('x-livewire') && $request->hasHeader('referer');
    }
}

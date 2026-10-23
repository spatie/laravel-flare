<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Support\Arr;
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
                redactor: $this->redactor,
            );

            return [
                ...$attributes,
                ...$livewireAttributesProvider->toArray(),
            ];
        } catch (Throwable) {
            return $attributes;
        }
    }

    protected function getRequest(): array
    {
        $payload = parent::getRequest();

        $components = $payload['http.request.body.contents']['components'] ?? null;

        if (! is_array($components)) {
            return $payload;
        }

        $payload['http.request.body.contents']['components'] = array_map(
            fn (mixed $component) => is_array($component)
                ? $this->censorLivewireComponent($component)
                : $component,
            $components,
        );

        return $payload;
    }

    /**
     * Livewire sends each component's state as a JSON encoded snapshot, which the
     * redactor cannot look into. Its updates are keyed by dotted property paths.
     *
     * @param array<string, mixed> $component
     *
     * @return array<string, mixed>
     */
    protected function censorLivewireComponent(array $component): array
    {
        if (is_string($component['snapshot'] ?? null)) {
            $component['snapshot'] = $this->censorLivewireSnapshot($component['snapshot']);
        }

        if (is_array($component['updates'] ?? null)) {
            foreach ($component['updates'] as $property => $value) {
                if (! is_string($property)) {
                    continue;
                }

                $component['updates'][$property] = $this->censorLivewireProperty($property, $value);
            }
        }

        return $component;
    }

    protected function censorLivewireSnapshot(string $snapshot): string
    {
        $decoded = json_decode($snapshot, true);

        if (! is_array($decoded) || ! is_array($decoded['data'] ?? null)) {
            return $snapshot;
        }

        $decoded['data'] = $this->redactor->censorBody($decoded['data']);

        return json_encode($decoded) ?: '';
    }

    protected function censorLivewireProperty(string $property, mixed $value): mixed
    {
        $censored = $this->redactor->censorBody(Arr::undot([$property => $value]));

        foreach (explode('.', $property) as $segment) {
            if (! is_array($censored)) {
                return $censored;
            }

            $censored = $censored[$segment];
        }

        return $censored;
    }

    protected function isRunningLiveWire(LaravelRequest $request): bool
    {
        return $request->hasHeader('x-livewire') && $request->hasHeader('referer');
    }
}

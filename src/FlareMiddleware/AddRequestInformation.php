<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Illuminate\Http\Request as LaravelRequest;
use Spatie\FlareClient\FlareMiddleware\AddRequestInformation as BaseAddRequestInformation;
use Spatie\FlareClient\Support\Redactor;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Spatie\LaravelFlare\AttributesProviders\LaravelUserAttributesProvider;
use Spatie\LaravelFlare\Support\LivewireComponentFinder;

class AddRequestInformation extends BaseAddRequestInformation
{
    protected bool $includeLivewireComponents = false;

    /** @var array<string> */
    protected array $ignoreLivewireComponents = [];

    public function __construct(
        Redactor $redactor,
        protected LivewireComponentFinder $livewireComponentFinder,
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
        // Resolve the request from the container at call time. This middleware is bound as a
        // singleton, so a constructor-injected request would go stale between requests under
        // long-running runtimes (Octane, Vapor, RoadRunner).
        $request = app(LaravelRequest::class);

        $attributes = (new LaravelRequestAttributesProvider(
            $this->redactor,
            $this->livewireComponentFinder,
            $request,
            includeContents: true,
            includeLivewireComponents: $this->includeLivewireComponents,
            ignoreLivewireComponents: $this->ignoreLivewireComponents,
        ))->toArray();

        if ($user = LaravelUserAttributesProvider::fromRequest($request)) {
            $attributes = [...$attributes, ...$user->toArray()];
        }

        return $attributes;
    }
}

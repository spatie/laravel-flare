<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Closure;
use Illuminate\Http\Request as LaravelRequest;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider;
use Spatie\FlareClient\FlareMiddleware\AddRequestInformation as BaseAddRequestInformation;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;

class AddRequestInformation extends BaseAddRequestInformation
{
    protected bool $includeLivewireComponents = false;

    protected array $ignoreLivewireComponents = [];

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            $container->get(RequestAttributesProvider::class),
            $config,
        );
    }

    public function __construct(
        RequestAttributesProvider $attributesProvider,
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

        if($this->attributesProvider instanceof LaravelRequestAttributesProvider) {
            return $this->attributesProvider->toArray(
                $request,
                includeContents: true,
                includeLivewireComponents: $this->includeLivewireComponents,
                ignoreLivewireComponents: $this->ignoreLivewireComponents
            );
        }

        return $this->attributesProvider->toArray(
            $request,
            includeContents: true,
        );
    }
}

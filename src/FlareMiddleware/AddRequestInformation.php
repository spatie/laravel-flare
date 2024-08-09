<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Illuminate\Http\Request as LaravelRequest;
use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider;
use Spatie\FlareClient\FlareMiddleware\AddRequestInformation as BaseAddRequestInformation;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Symfony\Component\HttpFoundation\Request;

class AddRequestInformation extends BaseAddRequestInformation
{
    protected bool $includeLivewireComponents = false;

    public function __construct($config)
    {
        parent::__construct($config);

        $this->includeLivewireComponents = $config['include_livewire_components'] ?? false;
    }

    protected function isRunningInConsole(): bool
    {
        return app()->runningInConsole();
    }

    protected function getRequest(): LaravelRequest
    {
        return app(LaravelRequest::class);
    }

    protected function buildProvider(Request $request): RequestAttributesProvider
    {
        return new LaravelRequestAttributesProvider(
            $this->censorBodyFields,
            $this->censorRequestHeaders,
            $this->removeIp,
            $this->includeLivewireComponents
        );
    }
}

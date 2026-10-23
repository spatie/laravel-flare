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

    /**
     * Censors the session and remember me cookies. Laravel decrypts cookies before
     * Flare reads them, so these hold the plain session ID and remember token. The
     * Cookie header is censored by default for the same reason.
     */
    protected function getCookies(): array
    {
        $cookies = parent::getCookies();

        if (empty($cookies)) {
            return [];
        }

        $sessionCookie = config('session.cookie');

        $loginCookies = array_filter(
            array_map('strval', array_keys($cookies['http.request.cookies'])),
            fn (string $name) => $name === $sessionCookie || str_starts_with($name, 'remember_'),
        );

        $cookies['http.request.cookies'] = (new Redactor(censorBodyFields: $loginCookies))
            ->censorBody($cookies['http.request.cookies']);

        return $cookies;
    }

    protected function isRunningLiveWire(LaravelRequest $request): bool
    {
        return $request->hasHeader('x-livewire') && $request->hasHeader('referer');
    }
}

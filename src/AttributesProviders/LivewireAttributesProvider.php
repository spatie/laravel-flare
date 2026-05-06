<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Spatie\FlareClient\Contracts\AttributesProvider;
use Spatie\LaravelFlare\Support\LivewireComponentFinder;

class LivewireAttributesProvider implements AttributesProvider
{
    /** @param array<string> $ignoreLivewireComponents */
    public function __construct(
        protected LivewireComponentFinder $livewireComponentFinder,
        protected Request $request,
        protected array $ignoreLivewireComponents = [],
    ) {
    }

    public function toArray(): array
    {
        $manager = $this->livewireComponentFinder->manager();
        $originalUrl = $manager?->originalUrl();

        return [
            'http.request.method' => $manager?->originalMethod(),
            'url.full' => $originalUrl,
            'url.scheme' => $originalUrl !== null ? parse_url($originalUrl, PHP_URL_SCHEME) : null,
            'url.path' => $originalUrl !== null ? parse_url($originalUrl, PHP_URL_PATH) : null,
            'url.query' => $originalUrl !== null ? parse_url($originalUrl, PHP_URL_QUERY) : null,
            'livewire.components' => $this->getLivewire(),
        ];
    }

    protected function getLivewire(): array
    {
        if ($this->request->has('components')) {
            $data = [];

            foreach ($this->request->input('components') as $component) {
                $snapshot = json_decode($component['snapshot'], true);

                $class = $this->livewireComponentFinder->findClass($snapshot['memo']['name']);

                if (in_array($class, $this->ignoreLivewireComponents)) {
                    continue;
                }

                $data[] = [
                    'component_class' => $class ?? null,
                    'data' => $snapshot['data'],
                    'memo' => $snapshot['memo'],
                    'updates' => $this->resolveUpdates($component['updates']),
                    'calls' => $component['calls'],
                ];
            }

            return $data;
        }

        $componentId = $this->request->input('fingerprint.id');
        $componentAlias = $this->request->input('fingerprint.name');

        if ($componentAlias === null) {
            return [];
        }

        try {
            $componentClass = $this->livewireComponentFinder->manager()?->getClass($componentAlias);
        } catch (Exception) {
            $componentClass = null;
        }

        $updates = $this->request->input('updates') ?? [];

        return [
            [
                'component_class' => $componentClass,
                'component_alias' => $componentAlias,
                'component_id' => $componentId,
                'data' => $this->resolveData(),
                'updates' => $this->resolveUpdates($updates),
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function resolveData(): array
    {
        $data = $this->request->input('serverMemo.data') ?? [];

        $dataMeta = $this->request->input('serverMemo.dataMeta') ?? [];

        foreach ($dataMeta['modelCollections'] ?? [] as $key => $value) {
            $data[$key] = array_merge($data[$key] ?? [], $value);
        }

        foreach ($dataMeta['models'] ?? [] as $key => $value) {
            $data[$key] = array_merge($data[$key] ?? [], $value);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    protected function resolveUpdates(array $updates): array
    {
        return array_map(function (array $update) {
            $update['payload'] = Arr::except($update['payload'] ?? [], ['id']);

            return $update;
        }, $updates);
    }
}

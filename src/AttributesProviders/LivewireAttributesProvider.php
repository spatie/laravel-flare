<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Livewire\LivewireManager;
use Spatie\FlareClient\Contracts\AttributesProvider;
use Spatie\LaravelFlare\Support\LivewireComponentFinder;

class LivewireAttributesProvider implements AttributesProvider
{
    /** @param array<string> $ignore */
    public function __construct(
        protected LivewireComponentFinder $livewireComponentFinder,
        protected Request $request,
        protected LivewireManager $livewire,
        protected array $ignore = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'http.request.method' => $this->livewire->originalMethod(),
            'url.full' => $this->livewire->originalUrl(),
            'url.scheme' => parse_url($this->livewire->originalUrl(), PHP_URL_SCHEME),
            'url.path' => parse_url($this->livewire->originalUrl(), PHP_URL_PATH),
            'url.query' => parse_url($this->livewire->originalUrl(), PHP_URL_QUERY),
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

                if (in_array($class, $this->ignore)) {
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
            $componentClass = $this->livewire->getClass($componentAlias);
        } catch (Exception $e) {
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
        $updates = $this->request->input('updates') ?? [];

        return array_map(function (array $update) {
            $update['payload'] = Arr::except($update['payload'] ?? [], ['id']);

            return $update;
        }, $updates);
    }
}

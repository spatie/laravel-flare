<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Livewire\LivewireManager;

class LivewireAttributesProvider
{
    /**
     * @param array<string> $ignore
     */
    public function toArray(
        Request $request,
        LivewireManager $livewire,
        array $ignore = [],
    ): array {
        return [
            'http.request.method' => $livewire->originalMethod(),
            'url.full' => $livewire->originalUrl(),
            'url.scheme' => parse_url($livewire->originalUrl(), PHP_URL_SCHEME),
            'url.path' => parse_url($livewire->originalUrl(), PHP_URL_PATH),
            'url.query' => parse_url($livewire->originalUrl(), PHP_URL_QUERY),
            'flare.entry_point.value' => $livewire->originalUrl(),
            'livewire.components' => $this->getLivewire($request, $livewire, $ignore),
        ];
    }

    /**
     * @param array<string> $ignore
     */
    protected function getLivewire(Request $request, LivewireManager $livewireManager, array $ignore): array
    {
        if ($request->has('components')) {
            $data = [];

            foreach ($request->input('components') as $component) {
                $snapshot = json_decode($component['snapshot'], true);

                // Livewire v4
                if (class_exists(\Livewire\Finder\Finder::class)) {
                    $class = app(\Livewire\Finder\Finder::class)
                        ->resolveClassComponentClassName($snapshot['memo']['name']);
                } else {
                    // Livewire v3
                    $class = app(\Livewire\Mechanisms\ComponentRegistry::class)
                        ->getClass($snapshot['memo']['name']);
                }

                if (in_array($class, $ignore)) {
                    continue;
                }

                $data[] = [
                    'component_class' => $class ?? null,
                    'data' => $snapshot['data'],
                    'memo' => $snapshot['memo'],
                    'updates' => $this->resolveUpdates($request, $component['updates']),
                    'calls' => $component['calls'],
                ];
            }

            return $data;
        }

        $componentId = $request->input('fingerprint.id');
        $componentAlias = $request->input('fingerprint.name');

        if ($componentAlias === null) {
            return [];
        }

        try {
            $componentClass = $livewireManager->getClass($componentAlias);
        } catch (Exception $e) {
            $componentClass = null;
        }

        $updates = $request->input('updates') ?? [];

        return [
            [
                'component_class' => $componentClass,
                'component_alias' => $componentAlias,
                'component_id' => $componentId,
                'data' => $this->resolveData($request),
                'updates' => $this->resolveUpdates($request, $updates),
            ],
        ];
    }


    /** @return array<string, mixed> */
    protected function resolveData(Request $request): array
    {
        $data = $request->input('serverMemo.data') ?? [];

        $dataMeta = $request->input('serverMemo.dataMeta') ?? [];

        foreach ($dataMeta['modelCollections'] ?? [] as $key => $value) {
            $data[$key] = array_merge($data[$key] ?? [], $value);
        }

        foreach ($dataMeta['models'] ?? [] as $key => $value) {
            $data[$key] = array_merge($data[$key] ?? [], $value);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    protected function resolveUpdates(Request $request, array $updates): array
    {
        $updates = $request->input('updates') ?? [];

        return array_map(function (array $update) {
            $update['payload'] = Arr::except($update['payload'] ?? [], ['id']);

            return $update;
        }, $updates);
    }
}

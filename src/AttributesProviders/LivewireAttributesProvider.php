<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Livewire\LivewireManager;
use Livewire\Mechanisms\ComponentRegistry;

class LivewireAttributesProvider
{
    public function toArray(
        Request $request,
        LivewireManager $livewire,
    ): array {
        return [
            'http.request.method' => $livewire->originalMethod(),
            'url.full' => $livewire->originalUrl(),

            // TODO url.scheme, url.path & url.query might be incorrect

            'livewire.components' => $this->getLivewire($request, $livewire),
        ];
    }

    protected function getLivewire(Request $request, LivewireManager $livewireManager): array
    {
        if ($request->has('components')) {
            $data = [];

            foreach ($request->get('components') as $component) {
                $snapshot = json_decode($component['snapshot'], true);

                $class = app(ComponentRegistry::class)->getClass($snapshot['memo']['name']);

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

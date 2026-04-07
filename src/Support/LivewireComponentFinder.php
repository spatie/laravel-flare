<?php

namespace Spatie\LaravelFlare\Support;

class LivewireComponentFinder
{
    /*
     * These properties are not typed because the Livewire classes
     * may not exist depending on the installed version (v3 vs v4)
     * or when Livewire is not installed at all.
     *
     * Each property has three states:
     * - unset: not yet resolved
     * - object: resolved successfully
     * - null: tried resolving but not available
     */

    /** @var \Livewire\LivewireManager|null */
    protected $livewire;

    /** @var \Livewire\Finder\Finder|null */
    protected $finder;

    /** @var \Livewire\Factory\Factory|null */
    protected $factory;

    /** @var \Livewire\Mechanisms\ComponentRegistry|null */
    protected $componentRegistry;

    public function findClass(string $name): ?string
    {
        if ($factory = $this->resolveLivewireFactory()) {
            try {
                return $factory->resolveComponentClass($name);
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->resolveLivewireComponentRegistry()?->getClass($name);
    }

    public function isSingleFileComponent(string $name): bool
    {
        return $this->findSingleFileComponentFile($name) !== null;
    }

    public function findSingleFileComponentFile(string $name): ?string
    {
        return $this->resolveLivewireFinder()?->resolveSingleFileComponentPath($name);
    }

    public function findCurrentSingleFileComponentFile(): ?string
    {
        $component = $this->resolveLivewire()?->current();

        if (! $component) {
            return null;
        }

        return $this->findSingleFileComponentFile($component->getName());
    }

    /** @return \Livewire\LivewireManager|null */
    protected function resolveLivewire()
    {
        if (! isset($this->livewire)) {
            $this->livewire = app()->bound('livewire')
                ? app('livewire')
                : null;
        }

        return $this->livewire;
    }

    /** @return \Livewire\Finder\Finder|null */
    protected function resolveLivewireFinder()
    {
        if (! isset($this->finder)) {
            $this->finder = app()->bound('livewire.finder')
                ? app('livewire.finder')
                : null;
        }

        return $this->finder;
    }

    /** @return \Livewire\Factory\Factory|null */
    protected function resolveLivewireFactory()
    {
        if (! isset($this->factory)) {
            $this->factory = app()->bound('livewire.factory')
                ? app('livewire.factory')
                : null;
        }

        return $this->factory;
    }

    /** @return \Livewire\Mechanisms\ComponentRegistry|null */
    protected function resolveLivewireComponentRegistry()
    {
        if (! isset($this->componentRegistry)) {
            $this->componentRegistry = class_exists(\Livewire\Mechanisms\ComponentRegistry::class)
                ? app(\Livewire\Mechanisms\ComponentRegistry::class)
                : null;
        }

        return $this->componentRegistry;
    }
}

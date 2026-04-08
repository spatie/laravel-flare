<?php

namespace Spatie\LaravelFlare\Support;

class LivewireComponentFinder
{
    /*
     * These properties are not typed because the Livewire classes
     * may not exist depending on the installed version (v3 vs v4)
     * or when Livewire is not installed at all.
     */

    /** @var \Livewire\LivewireManager|null */
    protected $livewire;

    /** @var \Livewire\Finder\Finder|null */
    protected $finder;

    /** @var \Livewire\Factory\Factory|null */
    protected $factory;

    /** @var \Livewire\Mechanisms\ComponentRegistry|null */
    protected $componentRegistry;

    public function __construct()
    {
        $this->livewire = app()->bound('livewire')
            ? app('livewire')
            : null;

        // Livewire 4
        $this->finder = app()->bound('livewire.finder')
            ? app('livewire.finder')
            : null;

        $this->factory = app()->bound('livewire.factory')
            ? app('livewire.factory')
            : null;

        // Livewire 3
        $this->componentRegistry = class_exists(\Livewire\Mechanisms\ComponentRegistry::class)
            ? app(\Livewire\Mechanisms\ComponentRegistry::class)
            : null;
    }

    public function findClass(string $name): ?string
    {
        if ($this->factory) {
            try {
                return $this->factory->resolveComponentClass($name);
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->componentRegistry?->getClass($name);
    }

    public function isSingleFileComponent(string $name): bool
    {
        return $this->findSingleFileComponentFile($name) !== null;
    }

    public function findSingleFileComponentFile(string $name): ?string
    {
        return $this->finder?->resolveSingleFileComponentPath($name);
    }

    public function findCurrentSingleFileComponentFile(): ?string
    {
        $component = $this->livewire?->current();

        if (! $component) {
            return null;
        }

        return $this->findSingleFileComponentFile($component->getName());
    }
}
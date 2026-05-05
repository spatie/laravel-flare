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

    /** @var array<string, ?string> */
    protected array $classCache = [];

    /** @var array<string, ?string> */
    protected array $singleFileComponentFileCache = [];

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

    /** @return \Livewire\LivewireManager|null */
    public function manager()
    {
        return $this->livewire;
    }

    public function findClass(string $name): ?string
    {
        if (array_key_exists($name, $this->classCache)) {
            return $this->classCache[$name];
        }

        try {
            return $this->classCache[$name] = $this->factory?->resolveComponentClass($name)
                ?? $this->componentRegistry?->getClass($name);
        } catch (\Throwable) {
            return $this->classCache[$name] = null;
        }
    }

    public function isSingleFileComponent(string $name): bool
    {
        return $this->findSingleFileComponentFile($name) !== null;
    }

    public function findSingleFileComponentFile(string $name): ?string
    {
        return array_key_exists($name, $this->singleFileComponentFileCache)
            ? $this->singleFileComponentFileCache[$name]
            : $this->singleFileComponentFileCache[$name] = $this->finder?->resolveSingleFileComponentPath($name);
    }

    public function findCurrentComponentName(): ?string
    {
        $component = $this->livewire?->current();

        if (! $component instanceof \Livewire\Component) {
            return null;
        }

        return $component->getName();
    }

    public function findCurrentSingleFileComponentFile(): ?string
    {
        $name = $this->findCurrentComponentName();

        if ($name === null) {
            return null;
        }

        return $this->findSingleFileComponentFile($name);
    }
}

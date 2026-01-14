<?php

namespace Spatie\LaravelFlare\Tests\TestClasses;

use Livewire\LivewireManager;
use Spatie\LaravelFlare\Support\LivewireComponentClassFinder;

class FakeLivewireManager extends LivewireManager
{
    public $fakeAliases = [];

    public static function setUp(): self
    {
        $manager = new self();

        app()->instance(LivewireManager::class, $manager);

        return $manager;
    }

    public function isDefinitelyLivewireRequest()
    {
        return true;
    }

    public function getClass($alias)
    {
        if (isset($this->fakeAliases[$alias])) {
            return $this->fakeAliases[$alias];
        }

        return LivewireComponentClassFinder::findForComponentName($alias);
    }

    public function addAlias(string $alias, string $class): void
    {
        $this->fakeAliases[$alias] = $class;
    }
}

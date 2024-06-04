<?php

namespace Spatie\LaravelFlare\Solutions;

use Livewire\LivewireComponentsFinder;
use Spatie\Ignition\Contracts\RunnableSolution;
use Spatie\Ignition\Contracts\Solution;

class LivewireDiscoverSolution implements Solution
{
    protected string $customTitle;

    public function __construct(string $customTitle = '')
    {
        $this->customTitle = $customTitle;
    }

    public function getSolutionTitle(): string
    {
        return $this->customTitle;
    }

    public function getSolutionDescription(): string
    {
        return 'You might have forgotten to discover your Livewire components.';
    }

    public function getDocumentationLinks(): array
    {
        return [
            'Livewire: Artisan Commands' => 'https://laravel-livewire.com/docs/2.x/artisan-commands',
        ];
    }
}

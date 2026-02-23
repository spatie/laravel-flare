<?php

namespace Workbench\App\Livewire;

use Livewire\Component;

class Wired extends Component
{
    public string $name;

    public function render()
    {
        return <<<'HTML'
        <div>
            Name: {{ $name ?? 'No name set' }}

            <input type="text" wire:model.live="name" placeholder="Enter your name" class="border p-2 rounded" />
        </div>
        HTML;
    }
}

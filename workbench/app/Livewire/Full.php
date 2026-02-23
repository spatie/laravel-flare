<?php

namespace Workbench\App\Livewire;

use Livewire\Attributes\Modelable;
use Livewire\Component;

class Full extends Component
{
    public string $name;

    public int $count = 22;

    public function mount($name = null): void
    {
        $this->name = $name ?? 'John Doe';
    }

    public function triggerException()
    {
        throw new \Exception('Something went wrong');
    }

    public function render()
    {
        return <<<'HTML'
        <div>
            <p>Hi there {{ $name }}</p>

            <input type="text" wire:model.live="name" placeholder="Enter your name" class="border p-2 rounded" />
            <livewire:counter wire:model.live="count" />
            <livewire:counter />
            <livewire:random-user />
            <livewire:test.counter />
            <button wire:click="triggerException" class="mt-4 bg-red-500 text-white px-4 py-2 rounded">Trigger Exception</button>
            {{-- Knowing others is intelligence; knowing yourself is true wisdom. --}}
        </div>
        HTML;
    }


}

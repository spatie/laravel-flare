<?php

namespace Workbench\App\Livewire;

use Livewire\Component;

class Nested extends Component
{
    public int $count = 22;

    public function render()
    {
        return <<<'HTML'
        <div>
            HI there {{ $count }}
            <livewire:counter wire:model.live="count" />
        </div>
        HTML;
    }
}

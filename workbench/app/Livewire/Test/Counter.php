<?php

namespace Workbench\App\Livewire\Test;

use Livewire\Component;

class Counter extends Component
{
    public function render()
    {
        return <<<'HTML'
        <div>
            Hi there
            {{-- Nothing in the world is as soft and yielding as water. --}}
        </div>
        HTML;
    }
}

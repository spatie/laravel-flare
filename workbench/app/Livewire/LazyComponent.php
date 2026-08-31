<?php

namespace Workbench\App\Livewire;

use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class LazyComponent extends Component
{
    public int $count = 42;

    public function placeholder()
    {
        return view('livewire.lazy-placeholder');
    }

    public function render()
    {
        return view('livewire.lazy');
    }
}

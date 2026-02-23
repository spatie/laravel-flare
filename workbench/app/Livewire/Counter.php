<?php

namespace Workbench\App\Livewire;

use Livewire\Attributes\Modelable;
use Livewire\Component;

class Counter extends Component
{
    #[Modelable]
    public $count;

    public function mount(int $count = 1)
    {
        $this->count = $count;
    }

    public function increment()
    {
        $this->count++;
    }

    public function decrement()
    {
        $this->count--;
    }

    public function render()
    {
        return view('livewire.counter');
    }
}

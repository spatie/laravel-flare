<?php

namespace Workbench\App\Livewire;

use Exception;
use Livewire\Attributes\Modelable;
use Livewire\Component;

class MountException extends Component
{
    public function mount()
    {
        throw new Exception('We failed');
    }


    public function render()
    {
        return view('livewire.counter');
    }
}

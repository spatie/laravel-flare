<?php

namespace Workbench\App\Livewire;

use Livewire\Component;

class NestedViewException extends Component
{
    public string $title = 'Nested View Exception Test';

    public function render()
    {
        return view('livewire.nested-view-exception');
    }
}

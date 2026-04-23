<?php

namespace Workbench\App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class TestInlineComponent extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(public int $count)
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return <<<'blade'
<div>
    {{ $count }}
    <!-- If you do not have a consistent goal in life, you can not live it in a consistent way. - Marcus Aurelius -->
</div>
blade;
    }
}

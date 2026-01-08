<?php

namespace Workbench\App\View\Components\Deeper;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DeeperComponent extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
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
    I'm a deep component
    <!-- If you do not have a consistent goal in life, you can not live it in a consistent way. - Marcus Aurelius -->
</div>
blade;    }
}

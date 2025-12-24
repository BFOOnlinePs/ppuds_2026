<?php

namespace Modules\Core\View\Button;

use Illuminate\View\Component;
use Illuminate\View\View;

class Primary extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct() {}

    /**
     * Get the view/contents that represent the component.
     */
    public function render(): View|string
    {
        return view('core::components.button.primary');
    }
}

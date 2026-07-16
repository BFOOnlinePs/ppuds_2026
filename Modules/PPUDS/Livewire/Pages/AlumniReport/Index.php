<?php

namespace Modules\PPUDS\Livewire\Pages\AlumniReport;

use App\View\Components\AppLayout;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('ppuds::livewire.pages.alumni-report.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Alumni Report'), 'url' => route('alumni-report.index')],
            ],
        ]);
    }
}

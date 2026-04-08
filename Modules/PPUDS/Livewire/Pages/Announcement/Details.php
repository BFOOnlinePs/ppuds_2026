<?php

namespace Modules\PPUDS\Livewire\Pages\Announcement;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Tables\Concerns\InteractsWithTable;
use Livewire\Component;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Modules\PPUDS\Entities\Announcement;

class Details extends Component implements HasInfolists, HasForms
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public Announcement $announcementModel;

    public function mount($announcement)
    {
        $this->announcementModel = Announcement::findOrFail($announcement);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->announcementModel)
            ->schema([
                ImageEntry::make('image')
            ]);
    }

    public function render()
    {
        return view('ppuds::livewire.pages.announcement.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Announcement Details'), 'url' => route('announcements.details', $this->announcementModel)],
            ]
        ]);
    }
}

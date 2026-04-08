<?php

namespace Modules\Core\Livewire\Pages\Home;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Livewire\Component;
use Modules\PPUDS\Entities\Announcement;
use Livewire\Attributes\Computed;
use Modules\Core\Settings\GeneralSettings;

class Index extends Component implements HasForms, HasInfolists
{
    use InteractsWithInfolists;
    use InteractsWithForms;

    public function mount() {}

    #[Computed]
    public function settings()
    {
        return app(GeneralSettings::class);
    }

    #[Computed]
    public function getAnnouncements()
    {
        return Announcement::active()->get();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('Student Companies')),
            Section::make(__('Announcements'))
                ->schema([
                    ViewField::make('announcements_list')
                        ->view('ppuds::components.fields.announcements-view'),
                ]),
        ]);
    }

    public function render()
    {
        return view('core::livewire.pages.home.index')
            ->layout(AppLayout::class);
    }
}

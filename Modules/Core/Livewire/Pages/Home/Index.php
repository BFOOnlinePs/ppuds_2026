<?php

namespace Modules\Core\Livewire\Pages\Home;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Livewire;
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
use Modules\PPUDS\Entities\StudentCompany;

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

    #[Computed]
    public function getStudentCompanies()
    {
        return StudentCompany::whereHas('registration', fn($q) => $q->where('student_id', auth()->id()))->with(['company', 'branch'])->get();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('Student Companies'))
                ->schema([
                    // Livewire::make(\Modules\PPUDS\Livewire\Pages\StudentCompany\Index::class)
                    ViewField::make('student_companies_cards')
                        ->view('ppuds::components.fields.student-companies-cards'),

                ])
                ->visible(fn() => auth()->user()->hasRole('Student')),

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

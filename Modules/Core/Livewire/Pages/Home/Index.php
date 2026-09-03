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
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\AttendanceLastSevenDaysChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\CompanyLocationsChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\CompanySectorsChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\CurrentStudentsGenderChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\CurrentStudentsProgramChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\FieldVisitsLastSixMonthsChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\LeaveRequestsStatusChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\TrainingStatusChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\UsersByRoleChart;
use Modules\Core\Settings\GeneralSettings;
use Modules\PPUDS\Entities\Announcement;

class Index extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public function mount() {}

    public function chartWidgets(): array
    {
        return collect([
            UsersByRoleChart::class,
            CurrentStudentsGenderChart::class,
            CurrentStudentsProgramChart::class,
            TrainingStatusChart::class,
            CompanyLocationsChart::class,
            CompanySectorsChart::class,
            AttendanceLastSevenDaysChart::class,
            LeaveRequestsStatusChart::class,
            FieldVisitsLastSixMonthsChart::class,
        ])
            ->filter(fn (string $widget) => $widget::canView())
            ->values()
            ->all();
    }

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

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
use Modules\Core\Enums\UserRole;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\AttendanceLastSevenDaysChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\CurrentStudentsGenderChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\FieldVisitsLastSixMonthsChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\LeaveRequestsStatusChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\TrainingStatusChart;
use Modules\Core\Livewire\Pages\Home\Widget\Charts\UsersByRoleChart;
use Modules\Core\Settings\GeneralSettings;
use Modules\PPUDS\Entities\StudentCompany;

class Index extends Component implements HasForms, HasInfolists
{
    use InteractsWithInfolists;
    use InteractsWithForms;

    public function mount() {}

    public function chartWidgets(): array
    {
        return collect([
            UsersByRoleChart::class,
            CurrentStudentsGenderChart::class,
            TrainingStatusChart::class,
            AttendanceLastSevenDaysChart::class,
            LeaveRequestsStatusChart::class,
            FieldVisitsLastSixMonthsChart::class,
        ])
            ->filter(fn (string $widget) => $widget::canView())
            ->values()
            ->all();
    }

    public function isStudent(): bool
    {
        return auth()->user()?->hasRole(UserRole::STUDENT->value) ?? false;
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

    #[Computed]
    public function getStudentCompanies()
    {
        return StudentCompany::whereHas('registration', fn($q) => $q->where('student_id', auth()->id()))
            ->with(['company', 'branch', 'department'])
            ->latest()
            ->get();
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

<?php

namespace Modules\PPUDS\Livewire\Pages\StudentCompany;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Livewire\Component;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Details extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;
    use ScopesStudentCompanyVisibility;

    public ?array $data = [];

    public StudentCompany $studentCompanyModel;

    public function mount($studentCompany)
    {
        $studentCompany = StudentCompany::query()
            ->with([
                'registration',
                'student',
                'student.studentProfile',
                'company',
                'branch',
                'branch.workingHours',
                'department',
                'attendances',
            ])
            ->withAttendanceDays()
            ->withActualWorkingHours()
            ->tap(fn ($query) => $this->applyStudentCompanyVisibilityScope($query))
            ->findOrFail($studentCompany);

        $this->studentCompanyModel = $studentCompany;

        $this->form->fill($studentCompany->toArray());
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->studentCompanyModel)
            ->schema([
                Section::make('تفاصيل الشركة')
                    ->description('جميع المعلومات الخاصة بشركة التدريب.')
                    ->schema([
                        ImageEntry::make('logo')
                            ->label('شعار الشركة')
                            ->getStateUsing(function () {
                                return $this->studentCompanyModel->company->getImageAttribute() ?? null;
                            })
                            ->circular(),

                        TextEntry::make('company.name')
                            ->label('اسم الشركة')
                            ->weight('bold')
                            ->size('lg'),

                        TextEntry::make('branch.email')
                            ->label('البريد الإلكتروني')
                            ->icon('solar-mailbox-bold')
                            ->copyable(),

                        TextEntry::make('branch.phone')
                            ->label('رقم الهاتف')
                            ->icon('solar-phone-bold'),

                        TextEntry::make('address')
                            ->label('العنوان'),

                        TextEntry::make('company.description')
                            ->label('وصف الشركة')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('attendance')
                            ->label(__('Attendance'))
                            ->schema([
                                Livewire::make(\Modules\PPUDS\Livewire\Pages\StudentAttendance\Index::class, [
                                    'filters' => [
                                        'student_company_id' => $this->studentCompanyModel->id,
                                    ],
                                ]),
                            ]),
                        Tabs\Tab::make('attendance-reports')
                            ->label(__('Daily Reports'))
                            ->schema([
                                Livewire::make(\Modules\PPUDS\Livewire\Pages\StudentAttendanceReport\Index::class, [
                                    'filters' => [
                                        'student_company_id' => $this->studentCompanyModel->id,
                                    ],
                                ]),
                            ]),
                        Tabs\Tab::make('training-summary')
                            ->label(__('Training Summary'))
                            ->icon('heroicon-m-clipboard-document-check')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([

                                        Section::make(__('Attendance Details'))
                                            ->icon('heroicon-m-briefcase')
                                            ->schema([
                                                TextEntry::make('attendance_days')
                                                    ->label(__('Attendance Days'))
                                                    ->icon('heroicon-m-calendar-days')
                                                    ->weight('bold')
                                                    ->color('success')
                                                    ->size('lg'),

                                                TextEntry::make('actual_working_hours')
                                                    ->label(__('Actual Working Hours'))
                                                    ->icon('heroicon-m-clock')
                                                    ->weight('bold')
                                                    ->color('info')
                                                    ->size('lg'),
                                            ])->columns(2),

                                        Section::make(__('Academic Info'))
                                            ->icon('heroicon-m-academic-cap')
                                            ->schema([
                                                TextEntry::make('registration.semester')
                                                    ->label(__('Semester'))
                                                    ->badge(),

                                                TextEntry::make('registration.year')
                                                    ->label(__('Year'))
                                                    ->icon('heroicon-m-calendar')
                                                    ->weight('semibold'),
                                            ])->columns(2),

                                    ]),
                            ]),
                        Tabs\Tab::make('payments')
                            ->label(__('Payments'))
                            ->schema([
                                Livewire::make(\Modules\PPUDS\Livewire\Pages\Payment\Index::class, [
                                    'filters' => [
                                        'student_company_id' => $this->studentCompanyModel->id,
                                    ],
                                ]),
                            ]),
                        Tabs\Tab::make('leave-requests')
                            ->label(__('Leave Requests'))
                            ->schema([
                                Livewire::make(\Modules\PPUDS\Livewire\Pages\LeaveRequest\Index::class, [
                                    'filters' => [
                                        'student_company_id' => $this->studentCompanyModel->id,
                                    ],
                                ]),
                            ]),
                    ]),
            ]);
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student-company.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('New Student Company'), 'url' => '#'],
            ],
        ]);
    }
}

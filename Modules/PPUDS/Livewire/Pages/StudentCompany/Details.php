<?php

namespace Modules\PPUDS\Livewire\Pages\StudentCompany;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
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

class Details extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public ?array $data = [];

    public StudentCompany $studentCompanyModel;

    public function mount($studentCompany)
    {
        $studentCompany = StudentCompany::with([
            'registration',
            'student',
            'student.studentProfile',
            'company',
            'branch',
            'branch.workingHours',
            'department',
            'attendances',
        ])->withAttendanceDays()
            ->withActualWorkingHours()->findOrFail($studentCompany);
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
                            ->label(__('Attendance Reports'))
                            ->schema([

                            ]),
                        Tabs\Tab::make('training-summary')
                            ->label(__('Training Summary'))
                            ->schema([
                                TextEntry::make('attendance_days')
                                    ->label(__('Attendance Days'))
                                    ->columnSpanFull(),

                                TextEntry::make('actual_working_hours')
                                    ->label(__('Actual Working Hours'))
                                    ->columnSpanFull(),

                                TextEntry::make('registration.semester')
                                    ->label(__('Semester'))
                                    ->badge()
                                    ->columnSpanFull(),

                                TextEntry::make('registration.year')
                                    ->label(__('Year'))
                                    ->columnSpanFull(),
                            ]),
                        Tabs\Tab::make('payments')
                            ->label(__('Payments'))
                            ->schema([
                                // ...
                            ]),
                        Tabs\Tab::make('leave-requests')
                            ->label(__('Leave Requests'))
                            ->schema([
                                // ...
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

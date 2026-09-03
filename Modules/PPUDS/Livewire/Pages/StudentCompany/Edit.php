<?php

namespace Modules\PPUDS\Livewire\Pages\StudentCompany;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Enums\WeekDay;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentCompanyWorkingHour;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Edit extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use ScopesStudentCompanyVisibility;

    public ?array $data = [];

    public $record; // المتغير الذي يحمل السجل الحالي

    // استقبال المعامل من الراوت (تأكد أن الاسم يطابق ما في ملف routes/web.php)
    public function mount($studentCompany)
    {
        // 1. جلب السجل
        $this->record = $this->applyStudentCompanyVisibilityScope(StudentCompany::query())
            ->findOrFail($studentCompany);

        // 2. تعبئة النموذج بالبيانات الحالية
        $formData = $this->record->toArray();

        $formData['working_hours'] = $this->record->workingHours
            ->map(fn (StudentCompanyWorkingHour $workingHour): array => [
                'day' => $workingHour->day?->value,
                'is_closed' => (bool) $workingHour->is_closed,
                'start_time' => $workingHour->start_time?->format('H:i'),
                'end_time' => $workingHour->end_time?->format('H:i'),
            ])
            ->toArray();

        $this->form->fill($formData);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->record) // ربط النموذج بالسجل
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        // --- العمود الرئيسي (يسار) ---
                        Group::make()
                            ->columnSpan(['lg' => 2])
                            ->schema([

                                // 1. قسم بيانات الطالب والتسجيل
                                Section::make(__('Student & Registration Info'))
                                    ->icon('solar-user-id-bold-duotone')
                                    // 🔥 حل مشكلة التداخل والقائمة المختفية 🔥
                                    ->extraAttributes(['class' => '!overflow-visible relative z-20'])
                                    ->schema([
                                        Select::make('registration_id')
                                            ->label(__('Select Student Registration'))
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->prefixIcon('solar-document-text-linear')
                                            ->options(function () {
                                                return Registration::with(['student', 'course'])
                                                    ->get()
                                                    ->mapWithKeys(function ($reg) {
                                                        $studentName = $reg->student?->name ?? __('Unknown Student');
                                                        $courseName = $reg->course?->name ?? __('No Course');

                                                        return [$reg->id => "{$studentName} - {$courseName}"];
                                                    });
                                            }),
                                    ]),

                                // 2. قسم تفاصيل المكان (الشركات)
                                Section::make(__('Placement Details'))
                                    ->icon('solar-buildings-2-bold-duotone')
                                    // تقليل الـ z-index لهذا القسم لكي يظهر القسم العلوي فوقه
                                    ->extraAttributes(['class' => 'relative z-10'])
                                    ->schema([
                                        Grid::make(2)->schema([

                                            Select::make('company_id')
                                                ->label(__('Company'))
                                                ->required()
                                                ->options(Company::get()->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->live()
                                                // عند تغيير الشركة، نقوم بتصفير الفرع والقسم
                                                ->afterStateUpdated(function (Set $set) {
                                                    $set('branch_id', null);
                                                    $set('department_id', null);
                                                })
                                                ->prefixIcon('solar-city-linear'),

                                            Select::make('branch_id')
                                                ->label(__('Branch'))
                                                ->key('branchSelect')
                                                ->required()
                                                ->searchable()
                                                ->preload()
                                                ->live()
                                                ->afterStateUpdated(fn (Set $set) => $set('department_id', null))
                                                ->prefixIcon('solar-map-point-linear')
                                                ->placeholder(fn (Get $get) => $get('company_id') ? __('Select Branch') : __('Select Company First'))
                                                ->disabled(fn (Get $get) => ! $get('company_id'))
                                                ->options(fn (Get $get) => Branch::whereHas('companies', function ($query) use ($get) {
                                                    $query->where('company_id', $get('company_id'));
                                                })->get()->pluck('name', 'id')
                                                ),

                                            Select::make('department_id')
                                                ->label(__('Department'))
                                                ->key('deptSelect')
                                                ->required()
                                                ->searchable()
                                                ->preload()
                                                ->prefixIcon('solar-users-group-two-rounded-linear')
                                                ->placeholder(fn (Get $get) => $get('branch_id') ? __('Select Department') : __('Select Branch First'))
                                                ->disabled(fn (Get $get) => ! $get('branch_id'))
                                                ->options(function (Get $get) {
                                                    $branchId = $get('branch_id');

                                                    if (! $branchId) {
                                                        return [];
                                                    }

                                                    return CompanyDepartment::whereHas('branches', fn ($query) => $query->whereKey($branchId))
                                                        ->get()
                                                        ->pluck('name', 'id');
                                                })
                                                ->columnSpanFull(),
                                        ]),
                                    ]),

                                Section::make(__('Student Working Hours'))
                                    ->description(__('Leave it empty to follow the branch working hours'))
                                    ->icon('solar-clock-circle-bold-duotone')
                                    ->collapsible()
                                    ->schema([
                                        Repeater::make('working_hours')
                                            ->label(__('Weekly Schedule'))
                                            ->hiddenLabel()
                                            ->schema([
                                                Grid::make(4)->schema([
                                                    Select::make('day')
                                                        ->label(__('Day'))
                                                        ->options(WeekDay::class)
                                                        ->distinct()
                                                        ->required()
                                                        ->native(false)
                                                        ->columnSpan(1),

                                                    Toggle::make('is_closed')
                                                        ->label(__('Closed?'))
                                                        ->onColor('danger')
                                                        ->offColor('success')
                                                        ->inline(false)
                                                        ->live()
                                                        ->columnSpan(1),

                                                    Group::make([
                                                        TimePicker::make('start_time')
                                                            ->label(__('Start'))
                                                            ->seconds(false)
                                                            ->default('08:00')
                                                            ->required(fn (Get $get) => ! $get('is_closed')),

                                                        TimePicker::make('end_time')
                                                            ->label(__('End'))
                                                            ->seconds(false)
                                                            ->default('16:00')
                                                            ->required(fn (Get $get) => ! $get('is_closed')),
                                                    ])
                                                        ->visible(fn (Get $get) => ! $get('is_closed'))
                                                        ->columnSpan(2)
                                                        ->columns(2),
                                                ]),
                                            ])
                                            ->defaultItems(0)
                                            ->addActionLabel(__('Add Day'))
                                            ->reorderable(false)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // --- العمود الجانبي (يمين) ---
                        Group::make()
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                Section::make(__('Status & Settings'))
                                    ->icon('solar-settings-bold-duotone')
                                    ->schema([
                                        Select::make('status')
                                            ->label(__('Training Status'))
                                            ->required()
                                            ->options(TrainingStatus::class)
                                            ->native(false)
                                            ->prefixIcon('solar-flag-bold-duotone'),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function messages(): array
    {
        return [
            'data.registration_id.required' => __('Please select a student registration record.'),
            'data.company_id.required' => __('Please select a company.'),
            'data.branch_id.required' => __('Please select a branch.'),
            'data.department_id.required' => __('Please select a department.'),
            'data.status.required' => __('The status field is required.'),
        ];
    }

    public function save()
    {
        // $this->authorize("StudentCompany Update"); // Assuming permission update

        $data = $this->validatedData();

        $workingHoursData = $data['working_hours'] ?? [];
        $data = Arr::except($data, ['working_hours']);

        // منطق هام: إذا قام المستخدم بتغيير "سجل التسجيل"، يجب تحديث "الطالب" أيضاً
        if ($data['registration_id'] != $this->record->registration_id) {
            $registration = Registration::find($data['registration_id']);
            if ($registration) {
                $data['student_id'] = $registration->student_id;
            }
        }

        // تحديث السجل
        $this->record->update($data);

        $this->syncWorkingHours($workingHoursData);

        Toaster::success(__('Student company record updated successfully'));

        $this->redirect(route('student-companies.index'));
    }

    /**
     * Replaces the student's weekly schedule. An empty list clears it, which
     * makes the placement fall back to the branch working hours.
     */
    protected function syncWorkingHours(array $workingHoursData): void
    {
        $this->record->workingHours()->delete();

        foreach ($workingHoursData as $workingHour) {
            $isClosed = (bool) ($workingHour['is_closed'] ?? false);

            $this->record->workingHours()->create([
                'day' => $workingHour['day'],
                'is_closed' => $isClosed,
                'start_time' => $isClosed ? null : ($workingHour['start_time'] ?? null),
                'end_time' => $isClosed ? null : ($workingHour['end_time'] ?? null),
            ]);
        }

        $this->record->unsetRelation('workingHours');
    }

    protected function validatedData(): array
    {
        $data = $this->form->getState();

        $this->validatePlacement($data);

        return $data;
    }

    protected function validatePlacement(array $data): void
    {
        $branchBelongsToCompany = Branch::query()
            ->whereKey($data['branch_id'] ?? null)
            ->whereHas('companies', fn ($query) => $query->whereKey($data['company_id'] ?? null))
            ->exists();

        if (! $branchBelongsToCompany) {
            throw ValidationException::withMessages([
                'data.branch_id' => __('The selected branch does not belong to the selected company.'),
            ]);
        }

        $departmentBelongsToBranch = CompanyDepartment::query()
            ->whereKey($data['department_id'] ?? null)
            ->whereHas('branches', fn ($query) => $query->whereKey($data['branch_id'] ?? null))
            ->exists();

        if (! $departmentBelongsToBranch) {
            throw ValidationException::withMessages([
                'data.department_id' => __('The selected department does not belong to the selected branch.'),
            ]);
        }

        $this->guardAgainstPlacementChangeWithHistory($data);
    }

    protected function guardAgainstPlacementChangeWithHistory(array $data): void
    {
        $companyChanged = (int) ($data['company_id'] ?? 0) !== (int) $this->record->company_id;
        $branchChanged = (int) ($data['branch_id'] ?? 0) !== (int) $this->record->branch_id;
        $departmentChanged = (int) ($data['department_id'] ?? 0) !== (int) $this->record->department_id;

        if (! $companyChanged && ! $branchChanged && ! $departmentChanged) {
            return;
        }

        $hasHistory = $this->record->attendances()->exists()
            || $this->record->fieldVisits()->exists()
            || $this->record->leaveRequests()->exists()
            || $this->record->payments()->exists();

        if ($hasHistory) {
            throw ValidationException::withMessages([
                'data.company_id' => __('This placement already has attendance, field visits, leave requests, or payments recorded. Changing the company/branch/department here would wrongly attribute that history to the new company. Please mark this placement as finished and add a new placement for the new company instead.'),
            ]);
        }
    }

    public function render()
    {
        // تأكد من وجود ملف العرض (يمكنك نسخ ملف add.blade.php وتسميته edit.blade.php)
        return view('ppuds::livewire.pages.student-company.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Student Companies'), 'url' => route('student-companies.index')],
                ['title' => __('Edit Student Company'), 'url' => '#'], // Update breadcrumb
            ],
        ]);
    }
}

<?php

namespace Modules\PPUDS\Livewire\Pages\StudentCompany;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
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
        $this->form->fill($this->record->toArray());
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

        // منطق هام: إذا قام المستخدم بتغيير "سجل التسجيل"، يجب تحديث "الطالب" أيضاً
        if ($data['registration_id'] != $this->record->registration_id) {
            $registration = Registration::find($data['registration_id']);
            if ($registration) {
                $data['student_id'] = $registration->student_id;
            }
        }

        // تحديث السجل
        $this->record->update($data);

        Toaster::success(__('Student company record updated successfully'));

        $this->redirect(route('student-companies.index'));
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

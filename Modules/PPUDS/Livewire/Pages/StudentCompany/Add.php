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
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings;

// نحتاجه لحقل الطالب
// لجلب قيم الحقول الأخرى (للفلترة)

class Add extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill([
            'registration_id' => $this->selectedRegistrationFromRequest(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(StudentCompany::class)
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Group::make()
                            ->columnSpan(['lg' => 2])
                            ->schema([

                                Section::make(__('Student & Registration Info'))
                                    ->icon('solar-user-id-bold-duotone')
                                    ->schema([
                                        Select::make('registration_id')
                                            ->label(__('Select Student Registration'))
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->prefixIcon('solar-document-text-linear')
                                            ->options(function () {
                                                return Registration::with(['student', 'course'])
                                                    ->where('semester', app(GeneralSettings::class)->semester_type->value)
                                                    ->where('year', app(GeneralSettings::class)->year)
                                                    ->get()
                                                    ->mapWithKeys(function ($reg) {
                                                        $semesterLabel = $reg->semester?->getLabel() ?? $reg->semester?->value;

                                                        return [$reg->id => "{$reg->student->studentProfile->student_number} - {$reg->student->name} - {$reg->course->name} ({$semesterLabel}/{$reg->year})"];
                                                    });
                                            }),
                                    ]),

                                Section::make(__('Placement Details'))
                                    ->icon('solar-buildings-2-bold-duotone')
                                    ->schema([
                                        Grid::make(2)->schema([

                                            Select::make('company_id')
                                                ->label(__('Company'))
                                                ->options(Company::get()->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->live()
                                                ->afterStateUpdated(function (Set $set) {
                                                    $set('branch_id', null);
                                                    $set('department_id', null);
                                                })
                                                ->prefixIcon('solar-city-linear'),

                                            Select::make('branch_id')
                                                ->label(__('Branch'))
                                                ->key('branchSelect')
                                                ->searchable()
                                                ->preload()
                                                ->live()
                                                ->afterStateUpdated(fn (Set $set) => $set('department_id', null))
                                                ->prefixIcon('solar-map-point-linear')
                                                ->placeholder(fn (Get $get) => $get('company_id') ? __('Select Branch') : __('Select Company First'))
                                                ->disabled(fn (Get $get) => ! $get('company_id'))
                                                ->options(
                                                    fn (Get $get) => Branch::whereHas('companies', function ($query) use ($get) {
                                                        $query->where('company_id', $get('company_id'));
                                                    })->get()->pluck('name', 'id')
                                                ),

                                            Select::make('department_id')
                                                ->label(__('Department'))
                                                ->key('deptSelect')
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
                                            ->default(TrainingStatus::AVAILABLE)
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
            'data.status.required' => __('The status field is required.'),
        ];
    }

    private function selectedRegistrationFromRequest(): ?int
    {
        $registrationId = request()->integer('registration_id');

        if (! $registrationId) {
            return null;
        }

        return Registration::query()
            ->whereKey($registrationId)
            ->where('semester', app(GeneralSettings::class)->semester_type->value)
            ->where('year', app(GeneralSettings::class)->year)
            ->value('id');
    }

    public function save()
    {
        $this->authorize('StudentCompany Create');

        $this->validate();

        $data = $this->data;

        $data['created_by'] = auth()->id();

        $registration = Registration::findOrFail($data['registration_id']);
        $data['student_id'] = $registration->student_id;

        StudentCompany::create($data);

        Toaster::success(__('Student company record created successfully'));

        $this->redirect(route('student-companies.index'));
    }

    public function render()
    {
        // تأكد من إنشاء ملف الـ Blade في المسار المذكور
        return view('ppuds::livewire.pages.student-company.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Student Companies'), 'url' => route('student-companies.index')],
                ['title' => __('New Student Company'), 'url' => '#'],
            ],
        ]);
    }
}

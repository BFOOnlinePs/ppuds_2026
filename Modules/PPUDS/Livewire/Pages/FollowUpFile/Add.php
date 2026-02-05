<?php

namespace Modules\PPUDS\Livewire\Pages\FollowUpFile;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden; // نحتاجه لحقل الطالب
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get; // لجلب قيم الحقول الأخرى (للفلترة)
use Illuminate\Support\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\FollowUp;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Enums\TrainingStatus;

class Add extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(FollowUp::class)
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
                                                ->get()
                                                ->mapWithKeys(function ($reg) {
                                                    return [$reg->id => "{$reg->student->name} - {$reg->course->name} ({$reg->semester}/{$reg->year})"];
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
                                            ->afterStateUpdated(fn (Select $component) => $component->getContainer()->getComponent('branchSelect')->state(null)) // تصفير الفرع عند تغيير الشركة
                                            ->prefixIcon('solar-city-linear'),

                                        Select::make('branch_id')
                                            ->label(__('Branch'))
                                            ->key('branchSelect')
                                            ->searchable()
                                            ->preload()
                                            ->prefixIcon('solar-map-point-linear')
                                            ->placeholder(fn (Get $get) => $get('company_id') ? __('Select Branch') : __('Select Company First'))
                                            ->disabled(fn (Get $get) => ! $get('company_id'))
                                            ->options(fn (Get $get) =>
                                                Branch::whereHas('companies', function ($query) use ($get) {
                                                    $query->where('company_id', $get('company_id'));
                                                })->get()->pluck('name', 'id')
                                            ),

                                        Select::make('department_id')
                                            ->label(__('Department'))
                                            ->searchable()
                                            ->preload()
                                            ->prefixIcon('solar-users-group-two-rounded-linear')
                                            ->disabled(fn (Get $get) => ! $get('company_id'))
                                            ->options(fn (Get $get) =>
                                                CompanyDepartment::get()->pluck('name', 'id')
                                            )
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
                                        ->options(TrainingStatus::class) // يدعم الـ Enum مباشرة كما فعلنا سابقاً
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

    public function save()
    {
        $this->authorize("FollowUp Create");

        $this->validate();

        $data = $this->data;

        $data['created_by'] = auth()->id();

        $registration = Registration::findOrFail($data['registration_id']);
        $data['student_id'] = $registration->student_id;

        FollowUp::create($data);

        Toaster::success(__('Follow-up record created successfully'));

        $this->redirect(route('follow-ups.index'));
    }

    public function render()
    {
        // تأكد من إنشاء ملف الـ Blade في المسار المذكور
        return view('ppuds::livewire.pages.follow-up.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Follow Ups'), 'url' => route('follow-ups.index')],
                ['title' => __('New Follow Up'), 'url' => '#'],
            ]
        ]);
    }
}

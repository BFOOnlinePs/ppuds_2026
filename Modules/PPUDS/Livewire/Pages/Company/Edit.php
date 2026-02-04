<?php

namespace Modules\PPUDS\Livewire\Pages\Company;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group; // تمت الاضافة
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs; // تمت الاضافة
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade; // تمت الاضافة
use Illuminate\Support\HtmlString; // تمت الاضافة
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Enums\CompanyStatus;

class Edit extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];
    public Company $company;

    public function mount(Company $company)
    {
        $this->company = $company;

        // 1. تعبئة البيانات الأساسية للشركة
        $formData = $company->toArray();

        // 2. تعبئة الفروع والأقسام يدوياً
        $formData['branches'] = $company->branches->map(function ($branch) {
            return [
                'id'           => $branch->id,
                'name'         => $branch->name,
                'email'        => $branch->email,
                'phone'        => $branch->phone,
                'country_id'   => $branch->country_id,
                'city_id'      => $branch->city_id,
                'latitude'     => $branch->latitude,
                'longitude'    => $branch->longitude,
                'opening_time' => $branch->opening_time,
                'closing_time' => $branch->closing_time,

                // جلب الأقسام المرتبطة بهذا الفرع
                'departments' => $branch->departments->map(function ($dept) {
                    return [
                        'name'    => $dept->name,
                        'user_id' => $dept->pivot->user_id,
                    ];
                })->toArray(),
            ];
        })->toArray();

        $this->form->fill($formData);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->company)
            ->schema([
                Wizard::make([
                    // --- الخطوة الأولى: معلومات الشركة ---
                    Wizard\Step::make(__('General Profile'))
                        ->label(__('Company Profile'))
                        ->icon('solar-buildings-3-bold-duotone')
                        ->description(__('Basic identity and categorization'))
                        ->schema([
                            Grid::make(['default' => 1, 'lg' => 3])
                                ->schema([
                                    // العمود الأيمن (الرئيسي)
                                    Group::make()
                                        ->columnSpan(['lg' => 2])
                                        ->schema([
                                            Section::make(__('Company Identity'))
                                                ->icon('solar-document-text-bold-duotone')
                                                ->schema([
                                                    Grid::make(2)->schema([
                                                        TextInput::make('name')
                                                            ->label(__('Company Name'))
                                                            ->required()
                                                            ->prefixIcon('solar-pen-new-square-linear')
                                                            ->placeholder(__('e.g. Acme Corporation'))
                                                            ->columnSpan(1),
                                                        TextInput::make('website')
                                                            ->label(__('Website URL'))
                                                            ->url()
                                                            ->prefixIcon('solar-global-linear')
                                                            ->placeholder('https://example.com')
                                                            ->columnSpan(1),
                                                    ]),
                                                    Textarea::make('description')
                                                        ->label(__('About Company'))
                                                        ->rows(4)
                                                        ->columnSpanFull(),
                                                ]),
                                        ]),

                                    // العمود الأيسر (الجانبي)
                                    Group::make()
                                        ->columnSpan(['lg' => 1])
                                        ->schema([
                                            Section::make(__('Media & Status'))
                                                ->icon('solar-gallery-check-bold-duotone')
                                                ->schema([
                                                    SpatieMediaLibraryFileUpload::make('logo')
                                                        ->label(__('Company Logo'))
                                                        ->collection('logo')
                                                        ->disk('companies')
                                                        ->model($this->company) // ضروري جداً في التعديل
                                                        ->image()
                                                        ->imageEditor()
                                                        ->circleCropper()
                                                        ->columnSpanFull(),

                                                    Select::make('company_category_id')
                                                        ->label(__('Category'))
                                                        ->prefixIcon('solar-tag-price-linear')
                                                        ->required()
                                                        ->options(CompanyCategory::get()->pluck('name', 'id'))
                                                        ->searchable()
                                                        ->preload(),

                                                    Select::make('status')
                                                        ->label(__('Status'))
                                                        ->required()
                                                        ->prefixIcon('solar-power-linear')
                                                        ->options(CompanyStatus::options())
                                                        ->native(false),
                                                ]),
                                        ]),
                                ]),
                        ]),

                    // --- الخطوة الثانية: الفروع والأقسام ---
                    Wizard\Step::make(__('Structure'))
                        ->label(__('Branches & Operations'))
                        ->icon('solar-shop-2-bold-duotone')
                        ->description(__('Manage branches, locations, and departments'))
                        ->schema([
                            Repeater::make('branches')
                                ->label(__('Branches'))
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? __('New Branch'))
                                ->addActionLabel(__('Add New Branch'))
                                ->minItems(1)
                                ->collapsible()
                                ->cloneable()
                                ->grid(1)
                                ->extraAttributes(['class' => 'gap-6'])
                                ->schema([
                                    // هام جداً: معرف الفرع للتحديث
                                    TextInput::make('id')->hidden(),

                                    Group::make()
                                        ->schema([
                                            Tabs::make('Branch Settings')
                                                ->tabs([
                                                    // 1. تبويب المعلومات
                                                    Tabs\Tab::make(__('Overview'))
                                                        ->icon('solar-info-circle-bold-duotone')
                                                        ->schema([
                                                            Grid::make(2)->schema([
                                                                TextInput::make('name')
                                                                    ->label(__('Branch Name'))
                                                                    ->required()
                                                                    ->default(__('Main Branch'))
                                                                    ->prefixIcon('solar-shop-linear')
                                                                    ->columnSpanFull(),

                                                                TextInput::make('email')
                                                                    ->label(__('Contact Email'))
                                                                    ->email()
                                                                    ->prefixIcon('solar-letter-linear'),

                                                                TextInput::make('phone')
                                                                    ->label(__('Phone Number'))
                                                                    ->tel()
                                                                    ->prefixIcon('solar-phone-calling-linear'),

                                                                Section::make(__('Working Hours'))
                                                                    ->icon('solar-clock-circle-bold-duotone')
                                                                    ->schema([
                                                                        Grid::make(2)->schema([
                                                                            TimePicker::make('opening_time')
                                                                                ->label(__('Opening'))
                                                                                ->seconds(false)
                                                                                ->required()
                                                                                ->prefixIcon('solar-sun-fog-linear'),
                                                                            TimePicker::make('closing_time')
                                                                                ->label(__('Closing'))
                                                                                ->seconds(false)
                                                                                ->required()
                                                                                ->prefixIcon('solar-moon-linear'),
                                                                        ]),
                                                                    ])->compact()->columnSpanFull()
                                                                    ->extraAttributes(['class' => 'bg-gray-50/50']),
                                                            ]),
                                                        ]),

                                                    // 2. تبويب الموقع
                                                    Tabs\Tab::make(__('Location'))
                                                        ->icon('solar-map-point-bold-duotone')
                                                        ->schema([
                                                            Grid::make(2)->schema([
                                                                Select::make('country_id')
                                                                    ->label(__('Country'))
                                                                    ->options(Country::all()->pluck('name', 'id'))
                                                                    ->searchable()
                                                                    ->required()
                                                                    ->live()
                                                                    ->prefixIcon('solar-flag-linear')
                                                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),

                                                                Select::make('city_id')
                                                                    ->label(__('City'))
                                                                    ->options(function (Get $get) {
                                                                        $countryId = $get('country_id');
                                                                        if (! $countryId) return [];
                                                                        return City::whereHas('governorate', function (Builder $query) use ($countryId) {
                                                                            $query->where('country_id', $countryId);
                                                                        })->get()->pluck('name', 'id');
                                                                    })
                                                                    ->searchable()
                                                                    ->prefixIcon('solar-city-linear')
                                                                    ->required(),

                                                                TextInput::make('latitude')->numeric()->placeholder('31.xxxx')
                                                                    ->prefixIcon('solar-map-arrow-up-linear'),
                                                                TextInput::make('longitude')->numeric()->placeholder('35.xxxx')
                                                                    ->prefixIcon('solar-map-arrow-right-linear'),
                                                            ]),
                                                        ]),

                                                    // 3. تبويب الأقسام
                                                    Tabs\Tab::make(__('Departments & Staff'))
                                                        ->icon('solar-users-group-rounded-bold-duotone')
                                                        ->badge(fn (Get $get) => count($get('departments') ?? []))
                                                        ->schema([
                                                            Repeater::make('departments')
                                                                ->hiddenLabel()
                                                                ->schema([
                                                                    Grid::make(2)->schema([
                                                                        Select::make('name')
                                                                            ->label(__('Department'))
                                                                            ->required()
                                                                            ->searchable()
                                                                            ->preload()
                                                                            ->prefixIcon('solar-case-minimalistic-linear')
                                                                            ->options(function () {
                                                                                return CompanyDepartment::get()
                                                                                    ->pluck('name', 'name')
                                                                                    ->unique()
                                                                                    ->toArray();
                                                                            })
                                                                            ->createOptionForm([
                                                                                TextInput::make('new_department_name')
                                                                                    ->label(__('Name'))
                                                                                    ->required()
                                                                                    ->maxLength(255),
                                                                            ])
                                                                            ->createOptionUsing(fn(array $data) => $data['new_department_name']),

                                                                        Select::make('user_id')
                                                                            ->label(__('Supervisor'))
                                                                            ->required()
                                                                            ->searchable()
                                                                            ->preload()
                                                                            ->prefixIcon('solar-user-id-linear')
                                                                            ->options(fn() => User::role('Company Supervisor')->pluck('name', 'id'))
                                                                            ->getSearchResultsUsing(fn (string $search) => User::role('Company Supervisor')
                                                                                ->where('name', 'like', "%{$search}%")
                                                                                ->limit(50)
                                                                                ->pluck('name', 'id')
                                                                            )
                                                                            ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                                                                            ->createOptionForm([
                                                                                Grid::make(2)->schema([
                                                                                    TextInput::make('name')->required(),
                                                                                    TextInput::make('name_en')->required(),
                                                                                    TextInput::make('email')->required()->email(),
                                                                                    TextInput::make('phone')->required()->numeric(),
                                                                                    TextInput::make('password')->required()->password()->confirmed(),
                                                                                    TextInput::make('password_confirmation')->required()->password(),
                                                                                ])
                                                                            ])
                                                                            ->createOptionUsing(function (array $data) {
                                                                                $data['password'] = bcrypt($data['password']);
                                                                                $user = User::create($data);
                                                                                $user->assignRole('Company Supervisor');
                                                                                return $user->id;
                                                                            })
                                                                            ->required(),
                                                                    ]),
                                                                ])
                                                                ->defaultItems(0)
                                                                ->collapsible()
                                                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                                                ->addActionLabel(__('Add Department'))
                                                                ->reorderableWithButtons()
                                                                ->extraAttributes(['class' => 'border-l-4 border-primary-500 pl-4']),
                                                        ]),
                                                ])
                                        ])
                                ]),
                        ]),
                ])
                    ->columnSpan('full')
                    // زر الحفظ يظهر فقط في الخطوة الأخيرة
                    ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button
                        wire:click="save"
                        type="button"
                        icon="solar-diskette-bold"
                        color="success"
                    >
                        {{ __('Save') }}
                    </x-filament::button>
                BLADE)))
            ])
            ->statePath('data');
    }

    protected function messages(): array
    {
        return [
            'data.name.required' => __('The company name is required'),
            'data.website.url' => __('The website URL is invalid'),
            'data.company_category_id.required' => __('The company category is required'),
            'data.status.required' => __('The company status is required'),

            'data.branches.*.name.required' => __('The branch name is required'),
            'data.branches.*.email.email' => __('The branch email is invalid'),
            'data.branches.*.phone.numeric' => __('The phone number must contain only numbers'),
            'data.branches.*.country_id.required' => __('The branch country is required'),
            'data.branches.*.city_id.required' => __('The branch city is required'),
            'data.branches.*.opening_time.required' => __('The opening time is required'),
            'data.branches.*.closing_time.required' => __('The closing time is required'),

            'data.branches.*.departments.*.name.required' => __('The department name is required'),
            'data.branches.*.departments.*.user_id.required' => __('The department supervisor is required'),

            'data.branches.*.departments.*.user_id.create_option_form.name.required' => __('The supervisor name is required'),
            'data.branches.*.departments.*.user_id.create_option_form.email.required' => __('The supervisor email is required'),
            'data.branches.*.departments.*.user_id.create_option_form.email.unique' => __('This email is already taken'),
            'data.branches.*.departments.*.user_id.create_option_form.password.required' => __('The password is required'),
            'data.branches.*.departments.*.user_id.create_option_form.password.confirmed' => __('The password confirmation does not match'),
        ];
    }

    public function save()
    {
        $this->authorize("Company Update");

        $this->validate();

        $companyData = Arr::except($this->data, ['branches', 'logo']);
        $this->company->update($companyData);

        $this->form->model($this->company)->saveRelationships();$this->form->model($this->company)->saveRelationships();

        $this->saveBranchesAndDepartments();

        Toaster::success(__('Company updated successfully'));
        $this->redirect(route('companies.index'));
    }

    protected function saveBranchesAndDepartments()
    {
        $formBranches = $this->data['branches'] ?? [];
        $processedBranchIds = [];

        foreach ($formBranches as $branchData) {

            $branchId = $branchData['id'] ?? null;
            $departmentsData = $branchData['departments'] ?? [];

            $branchAttributes = Arr::except($branchData, ['departments', 'id']);

            $branch = null;

            if ($branchId) {
                $branch = Branch::find($branchId);
                if ($branch) {
                    $branch->update($branchAttributes);
                }
            } else {
                $branchAttributes['created_by'] = auth()->id();
                $branch = Branch::create($branchAttributes);
                $this->company->branches()->attach($branch->id, ['is_main' => false]);
            }

            if ($branch) {
                $processedBranchIds[] = $branch->id;
                $this->syncDepartmentsForBranch($branch, $departmentsData);
            }
        }

        $currentCompanyBranchIds = $this->company->branches()->pluck('branch_branches.id')->toArray();
        $branchesToDetach = array_diff($currentCompanyBranchIds, $processedBranchIds);

        if (!empty($branchesToDetach)) {
            $this->company->branches()->detach($branchesToDetach);
        }
    }

    protected function syncDepartmentsForBranch(Branch $branch, array $departmentsData)
    {
        $syncData = [];

        foreach ($departmentsData as $deptData) {
            $deptName = $deptData['name'];
            $userId = $deptData['user_id'] ?? null;

            $department = CompanyDepartment::whereTranslation('name', $deptName)->first();

            if (! $department) {
                $department = CompanyDepartment::create([
                    'name'       => $deptName,
                    'created_by' => auth()->id(),
                ]);
            }

            $syncData[$department->id] = ['user_id' => $userId];
        }

        $branch->departments()->sync($syncData);
    }

    public function render()
    {
        return view('ppuds::livewire.pages.company.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Companies List'), 'url' => route('companies.index')],
                ['title' => __('Edit Company'), 'url' => route('companies.edit', $this->company->id)],
            ]
        ]);
    }
}

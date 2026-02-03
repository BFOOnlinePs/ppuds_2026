<?php

namespace Modules\PPUDS\Livewire\Pages\Company;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
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
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\Textarea;
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
                        // هام جداً: قراءة المشرف من الجدول الوسيط (Pivot)
                        // تأكد أنك أضفت ->withPivot('user_id') في علاقة الموديل
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
                    // === الخطوة 1: البيانات العامة ===
                    Wizard\Step::make(__('General Info'))
                        ->schema([
                            Grid::make(4)->schema([
                                Grid::make(3)->columnSpan(3)->schema([
                                    Section::make(__('Company Information'))->schema([
                                        TextInput::make('name')->required()->label(__('Name')),
                                        TextInput::make('website')->url()->label(__('Website')),
                                        Textarea::make('description')->label(__('Description')),
                                    ]),
                                ]),
                                Grid::make(1)->columnSpan(1)->schema([
                                    SpatieMediaLibraryFileUpload::make('logo')
                                        ->label(__('Logo'))
                                        ->collection('logo')
                                        ->model($this->company)
                                        ->image(),

                                    Section::make()->schema([
                                        Select::make('company_category_id')
                                            ->label(__('Category'))
                                            ->required()
                                            ->options(CompanyCategory::get()->pluck('name', 'id'))
                                            ->searchable(),
                                    ]),

                                    Section::make()->schema([
                                        Select::make('status')
                                            ->label(__('Status'))
                                            ->required()
                                            ->options(CompanyStatus::options())
                                            ->searchable()
                                    ])
                                ])
                            ]),
                        ]),

                    // === الخطوة 2: الفروع والأقسام ===
                    Wizard\Step::make(__('Branches & Departments'))
                        ->icon('heroicon-m-building-storefront')
                        ->schema([
                            Repeater::make('branches')
                                ->label(__('Branches'))
                                ->itemLabel(fn(array $state): ?string => $state['name'] ?? __('New Branch'))
                                ->extraAttributes([
                                    'style' => 'background-color: #f3f4f6; border-radius: 0.5rem; padding: 1rem;'
                                ])
                                ->collapsible()
                                ->cloneable()
                                ->schema([
                                    TextInput::make('id')->hidden(),

                                    // 1. بيانات الفرع
                                    Section::make(__('Branch Details'))
                                        ->aside()
                                        ->icon('solar-buildings-2-bold')
                                        ->schema([
                                            TextInput::make('name')->required()->label(__('Branch Name')),
                                            Grid::make(2)->schema([
                                                TextInput::make('email')->label(__('Email'))->email(),
                                                TextInput::make('phone')->label(__('Phone'))->tel(),
                                            ]),
                                        ])->compact(),

                                    // 2. إدارة الأقسام
                                    Section::make(__('Departments'))
                                        ->description(__('Define departments for this specific branch.'))
                                        ->aside()
                                        ->extraAttributes([
                                            'style' => 'background-color: #f3f4f6; border-radius: 0.5rem; padding: 1rem;'
                                        ])
                                        ->schema([
                                            Repeater::make('departments')
                                                ->label(__('Departments List'))
                                                ->schema([
                                                    Select::make('name')
                                                        ->label(__('Department Name'))
                                                        ->required()
                                                        ->searchable()
                                                        ->preload()
                                                        ->options(function () {
                                                            return CompanyDepartment::get()
                                                                ->pluck('name', 'name')
                                                                ->unique()
                                                                ->toArray();
                                                        })
                                                        ->createOptionForm([
                                                            TextInput::make('new_department_name')
                                                                ->label(__('New Department Name'))
                                                                ->required()
                                                                ->maxLength(255),
                                                        ])
                                                        ->createOptionUsing(fn(array $data) => $data['new_department_name']),

                                                    // Select User (Supervisor)
                                                    Select::make('user_id')
                                                        ->label(__('User'))
                                                        ->required()
                                                        ->options(User::role('Company Supervisor')->pluck('name', 'id'))
                                                        ->searchable()
                                                        ->preload()
                                                        // هذا يضمن عرض الاسم الصحيح عند فتح الصفحة
                                                        ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                                                        ->live()
                                                        ->createOptionForm([
                                                            TextInput::make('name')->required(),
                                                            TextInput::make('name_en')->required(),
                                                            TextInput::make('email')->required()->email(),
                                                            TextInput::make('phone')->required()->numeric(),
                                                            TextInput::make('password')->required()->password(),
                                                        ])
                                                        ->createOptionUsing(function (array $data) {
                                                            $data['password'] = bcrypt($data['password']);
                                                            $user = User::create($data);
                                                            $user->assignRole('Company Supervisor');
                                                            return $user->id;
                                                        }),
                                                ])
                                                ->grid(2)
                                                ->defaultItems(0)
                                                ->collapsible()
                                                ->addActionLabel(__('Add New Department'))
                                                ->itemLabel(fn(array $state): ?string => $state['name'] ?? null),
                                        ]),

                                    // 3. الموقع
                                    Section::make(__('Location'))
                                        ->schema([
                                            Grid::make(2)->schema([
                                                Select::make('country_id')
                                                    ->label(__('Country'))
                                                    ->options(Country::get()->pluck('name', 'id'))
                                                    ->live()
                                                    ->required()
                                                    ->searchable()
                                                    ->afterStateUpdated(fn(Set $set) => $set('city_id', null)),
                                                Select::make('city_id')
                                                    ->label(__('City'))
                                                    ->options(fn(Get $get) => City::whereHas('governorate', fn($q) => $q->where('country_id', $get('country_id')))->get()->pluck('name', 'id'))
                                                    ->required()
                                                    ->searchable(),
                                            ]),
                                            Grid::make(2)->schema([
                                                TextInput::make('latitude')->numeric(),
                                                TextInput::make('longitude')->numeric(),
                                            ]),
                                        ])->compact(),

                                    // 4. أوقات العمل
                                    Section::make(__('Working Hours'))
                                        ->schema([
                                            Grid::make(2)->schema([
                                                TimePicker::make('opening_time')->label(__('Opening'))->seconds(false)->required(),
                                                TimePicker::make('closing_time')->label(__('Closing'))->seconds(false)->required(),
                                            ]),
                                        ])->compact(),
                                ])
                        ]),
                ])->columnSpan('full')
            ])
            ->statePath('data');
    }

    public function save()
    {
        $this->validate();

        // 1. تحديث بيانات الشركة
        $companyData = Arr::except($this->data, ['branches', 'logo']);
        $this->company->update($companyData);

        if (isset($this->data['logo'])) {
            // $this->company->addMedia(...) // لوجيك حفظ الصورة
        }

        // 2. معالجة الفروع والأقسام
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
                // تحديث فرع موجود
                $branch = Branch::find($branchId);
                if ($branch) {
                    $branch->update($branchAttributes);
                }
            } else {
                // إنشاء فرع جديد
                $branchAttributes['created_by'] = auth()->id();
                $branch = Branch::create($branchAttributes);
                $this->company->branches()->attach($branch->id, ['is_main' => false]);
            }

            if ($branch) {
                $processedBranchIds[] = $branch->id;
                // مزامنة الأقسام لهذا الفرع
                $this->syncDepartmentsForBranch($branch, $departmentsData);
            }
        }

        // --- التعديل هنا ---
        // استخدام اسم الجدول "branch_branches" بدلاً من "branches"
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

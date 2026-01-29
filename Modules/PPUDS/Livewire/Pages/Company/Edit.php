<?php

namespace Modules\PPUDS\Livewire\Pages\Company;

use App\View\Components\AppLayout;
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
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\Department;
use Modules\PPUDS\Enums\CompanyStatus;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public Company $company;

    public function mount(Company $company)
    {
        $this->company = $company;

        // 1. تحميل العلاقات (الفروع والأقسام) ليتم عرضها داخل الـ Repeaters
        $this->company->load(['branches.departments', 'media']);

        // 2. تعبئة الفورم بالبيانات الحالية
        // toArray() سيقوم تلقائياً بوضع مفتاح 'branches' ومفتاح 'departments' داخله
        $this->form->fill($this->company->toArray());
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
                                // ملاحظة: لا نستخدم relationship() هنا لأننا نتحكم بالحفظ يدوياً للعلاقات المعقدة
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? __('Branch'))
                                ->schema([
                                    // Hidden ID to identify updates vs creates
                                    TextInput::make('id')->hidden(),

                                    // بيانات الفرع
                                    Section::make(__('Branch Details'))->schema([
                                        TextInput::make('name')->required()->label(__('Name')),
                                        Grid::make(2)->schema([
                                            TextInput::make('email')->email(),
                                            TextInput::make('phone')->tel(),
                                        ]),
                                    ])->compact(),

                                    // الأقسام داخل الفرع
                                    Section::make(__('Departments'))
                                        ->schema([
                                            Repeater::make('departments')
                                                ->label(__('Departments List'))
                                                ->schema([
                                                    TextInput::make('id')->hidden(), // لتمييز التعديل
                                                    TextInput::make('name')->required()->label(__('Name')),
                                                ])
                                                ->grid(2)
                                                ->collapsible(),
                                        ]),

                                    // الموقع
                                    Section::make(__('Location'))->schema([
                                        Grid::make(2)->schema([
                                            Select::make('country_id')
                                                ->options(Country::get()->pluck('name', 'id'))
                                                ->live()
                                                ->required()
                                                ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),
                                            Select::make('city_id')
                                                ->options(fn (Get $get) => City::get()->pluck('name', 'id'))
                                                ->required(),
                                        ]),
                                        Grid::make(2)->schema([
                                            TextInput::make('latitude')->numeric()->required()->minValue(-90)->maxValue(90),
                                            TextInput::make('longitude')->numeric()->required()->minValue(-180)->maxValue(180),
                                        ]),
                                    ])->compact(),

                                    // أوقات العمل
                                    Section::make(__('Working Hours'))->schema([
                                        Grid::make(2)->schema([
                                            TimePicker::make('opening_time')->seconds(false)->required(),
                                            TimePicker::make('closing_time')->seconds(false)->required(),
                                        ]),
                                    ])->compact(),
                                ])
                                ->collapsible()
                                ->cloneable()
                        ]),
                ])->columnSpan('full')
            ])
            ->statePath('data');
    }

    public function save()
    {
        $this->validate();

        // 1. تحديث بيانات الشركة الأساسية
        $companyAttributes = Arr::except($this->data, ['branches', 'logo']);
        $this->company->update($companyAttributes);

        // 2. تحديث الشعار (يتم تلقائياً عبر Filament model binding لكن للتأكيد)
        $this->form->model($this->company)->saveRelationships();

        // 3. معالجة الفروع والأقسام (تحديث، إنشاء، حذف)
        $this->saveBranchesAndDepartments();

        Toaster::success(__('Company updated successfully'));
        $this->redirect(route('companies.index'));
    }

    protected function saveBranchesAndDepartments()
    {
        $formBranches = $this->data['branches'] ?? [];

        // جلب أرقام الفروع الحالية الموجودة في قاعدة البيانات لهذا الشركة
        $existingBranchIds = $this->company->branches()->pluck('branch_branches.id')->toArray();
        $submittedBranchIds = [];

        foreach ($formBranches as $branchData) {
            $branchId = $branchData['id'] ?? null;
            $departmentsData = $branchData['departments'] ?? [];

            // تنظيف البيانات
            $branchAttributes = Arr::except($branchData, ['departments', 'id']);
            $branchAttributes['created_by'] = auth()->id();

            if ($branchId) {
                // === تحديث فرع موجود ===
                $branch = Branch::find($branchId);
                if ($branch) {
                    $branch->update($branchAttributes);
                    $submittedBranchIds[] = $branchId;
                }
            } else {
                // === إنشاء فرع جديد ===
                $branch = Branch::create($branchAttributes);
                $this->company->branches()->attach($branch->id, ['is_main' => false]);
                $branchId = $branch->id; // نحتاجه للأقسام
            }

            // === معالجة الأقسام لهذا الفرع ===
            if ($branch) {
                $this->syncDepartments($branch, $departmentsData);
            }
        }

        // === حذف الفروع التي قام المستخدم بإزالتها من الـ Repeater ===
        // الفرق بين الموجود سابقاً والمقدم حالياً
        $branchesToDelete = array_diff($existingBranchIds, $submittedBranchIds);
        if (!empty($branchesToDelete)) {
            // فك الارتباط (Detach)
            $this->company->branches()->detach($branchesToDelete);

            // اختياري: إذا كنت تريد حذف الفرع نهائياً من السيستم
            Branch::destroy($branchesToDelete);
        }
    }

    protected function syncDepartments(Branch $branch, array $departmentsData)
    {
        $existingDeptIds = $branch->departments()->pluck('id')->toArray();
        $submittedDeptIds = [];

        foreach ($departmentsData as $deptData) {
            $deptId = $deptData['id'] ?? null;
            $name = $deptData['name']; // الاسم الذي نريد حفظه

            if ($deptId) {
                $department = CompanyDepartment::find($deptId);
                if ($department) {
                    $department->update(['name' => $name]);
                    $submittedDeptIds[] = $deptId;
                }
            } else {
                $branch->departments()->create([
                    'name' => $name,
                    'created_by' => auth()->id()
                ]);
            }
        }

        // حذف الأقسام التي تم إزالتها من الفورم
        $deptsToDelete = array_diff($existingDeptIds, $submittedDeptIds);
        if (!empty($deptsToDelete)) {
            CompanyDepartment::destroy($deptsToDelete);
        }
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

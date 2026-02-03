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
use Hash;
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
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Role;

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
            ->model(Company::class)
            ->schema([
                Wizard::make([
                    Wizard\Step::make(__('General Info'))
                        ->schema([
                            Grid::make(4)
                                ->schema([
                                    Grid::make(3)
                                        ->columnSpan(3)
                                        ->schema([
                                            Section::make(__('Company Information'))
                                                ->schema([
                                                    TextInput::make('name')
                                                        ->required()
                                                        ->label(__('Name')),
                                                    TextInput::make('website')
                                                        ->url()
                                                        ->label(__('Website')),
                                                    Textarea::make('description')
                                                        ->label(__('Description')),
                                                ]),
                                        ]),

                                    Grid::make(1)
                                        ->columnSpan(1)
                                        ->schema([
                                            SpatieMediaLibraryFileUpload::make('logo')
                                                ->label(__('Logo'))
                                                ->model(Company::class)
                                                ->collection('logo'),

                                            Section::make()
                                                ->schema([
                                                    Select::make('company_category_id')
                                                        ->label(__('Company Category'))
                                                        ->required()
                                                        ->options(CompanyCategory::get()->pluck('name', 'id'))
                                                        ->searchable()
                                                ]),

                                            Section::make()
                                                ->schema([
                                                    Select::make('status')
                                                        ->label(__('Company Status'))
                                                        ->required()
                                                        ->default(CompanyStatus::ACTIVE->value)
                                                        ->options(CompanyStatus::options())
                                                        ->searchable()
                                                ])
                                        ])
                                ]),
                        ]),

                    Wizard\Step::make(__('Branches & Departments'))
                        ->icon('heroicon-m-building-storefront')
                        ->schema([
                            Repeater::make('branches')
                                ->label(__('Branches'))
                                ->relationship('branches')
                                ->extraAttributes([
                                    'style' => 'background-color: #f3f4f6; border-radius: 0.5rem; padding: 1rem;'
                                ])
                                ->minItems(1)
                                ->defaultItems(1)
                                ->collapsible()
                                ->collapsed()
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? __('New Branch'))
                                ->cloneable()
                                ->schema([
                                    // 1. بيانات الفرع
                                    Section::make(__('Branch Details'))
                                        ->aside()
                                        ->icon('solar-buildings-2-bold')
                                        ->schema([
                                            TextInput::make('name')
                                                ->label(__('Branch Name'))
                                                ->required()
                                                ->default(__('Main Branch')),
                                            Grid::make(2)->schema([
                                                TextInput::make('email')->label(__('Email'))->email(),
                                                TextInput::make('phone')->label(__('Phone'))->tel(),
                                            ]),
                                        ])->compact(),

                                    // 2. إدارة الأقسام (تم إضافتها هنا لربطها بالفرع مباشرة)
                                    Section::make(__('Departments'))
                                        ->description(__('Define departments for this specific branch.'))
                                        ->aside() // لجعل العنوان جانبيًا مما يقلل الزحمة الرأسية
                                        ->extraAttributes([
                                            'style' => 'background-color: #f3f4f6; border-radius: 0.5rem; padding: 1rem;'
                                        ])
                                        ->schema([
                                            Repeater::make('departments')
                                                ->label(__('Departments List'))
                                                ->relationship('departments')
                                                ->schema([
                                                    Select::make('name')
                                                        ->label(__('Department Name'))
                                                        ->required()
                                                        ->searchable()
                                                        ->preload()
                                                        ->options(function () {
                                                            return \Modules\PPUDS\Entities\CompanyDepartment::get()
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
                                                        ->createOptionUsing(function (array $data) {
                                                            return $data['new_department_name'];
                                                        }),

                                                    Select::make('user_id')
                                                        ->label(__('User'))
                                                        ->relationship(
                                                            name: 'user',
                                                            titleAttribute: 'name',
                                                            modifyQueryUsing: fn (Builder $query) => $query->role('Company Supervisor')
                                                        )
                                                        ->searchable()
                                                        ->preload()
                                                        ->createOptionForm([
                                                            TextInput::make('name')->required(),
                                                            TextInput::make('name_en')->required(),
                                                            TextInput::make('email')->required()->email(),
                                                            TextInput::make('phone')->required()->numeric(),
                                                            TextInput::make('password')->required()->password(),
                                                        ])
                                                        ->createOptionUsing(function (array $data) {
                                                            $data['password'] = bcrypt($data['password']);

                                                            // 1. إنشاء المستخدم
                                                            $user = User::create($data);

                                                            // 2. إعطاء الصلاحية
                                                            $user->assignRole('Company Supervisor');

                                                            // 3. مهم جداً: إرجاع الـ ID ليتم اختياره تلقائياً
                                                            return $user->id;
                                                        })
                                                        ->required(),
                                                ])
                                                ->grid(2)
                                                ->defaultItems(0)
                                                ->collapsible()
                                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                                // تمييز زر الإضافة
                                                ->addActionLabel(__('Add New Department'))
                                        ]),

                                    // 3. الموقع
                                    Section::make(__('Location'))
                                        ->schema([
                                            Grid::make(2)->schema([
                                                Select::make('country_id')
                                                    ->label(__('Country'))
                                                    ->options(Country::all()->pluck('name', 'id'))
                                                    ->default(fn () => Country::whereTranslation('name', 'فلسطين')->orWhereTranslation('name', 'Palestine')->first()?->id)
                                                    ->searchable()
                                                    ->required()
                                                    ->live()
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
                                                    ->default(fn () => City::whereTranslation('name', 'الخليل')->orWhereTranslation('name', 'Hebron')->first()?->id)
                                                    ->searchable()
                                                    ->required(),
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
                                                TimePicker::make('opening_time')->label(__('Opening'))->seconds(false)->required()->default('08:00'),
                                                TimePicker::make('closing_time')->label(__('Closing'))->seconds(false)->required()->default('16:00'),
                                            ]),
                                        ])->compact(),
                                ])
                                ->grid(1)
                        ]),
                ])
                    ->columnSpan('full')
            ])
            ->statePath('data');
    }

    public function save()
    {
        $this->validate();

        // 1. إنشاء الشركة
        $companyData = \Illuminate\Support\Arr::except($this->data, ['branches', 'logo']);
        $companyData['created_by'] = auth()->id();

        $company = Company::create($companyData);

        if (isset($this->data['logo'])) {
            $company->addImage($this->data['logo']);
        }

        // 2. إنشاء الفروع
        if (!empty($this->data['branches'])) {

            foreach ($this->data['branches'] as $branchData) {

                $departmentsData = $branchData['departments'] ?? [];
                $branchCleanData = \Illuminate\Support\Arr::except($branchData, ['departments']);

                $branchCleanData['created_by'] = auth()->id();

                // إنشاء الفرع
                $branch = Branch::create($branchCleanData); // تأكد أن المودل Branch هو الصحيح

                // ربط الفرع بالشركة
                $company->branches()->attach($branch->id, ['is_main' => false]);

                // 3. معالجة الأقسام (لمنع التكرار)
                foreach ($departmentsData as $deptData) {

                    $deptName = $deptData['name'];

                    // --- [الحل لمنع التكرار] ---

                    // 1. نبحث هل يوجد قسم بهذا الاسم داخل *هذا الفرع* تحديداً؟
                    $existingDept = CompanyDepartment::where('branch_id', $branch->id)
                        ->whereTranslation('name', $deptName) // دالة البحث في الترجمة
                        ->first();

                    if ($existingDept) {
                        // 2. إذا وجد: نقوم بتحديثه فقط (مثلاً تحديث المدير إذا تغير)
                        $existingDept->update([
                            'user_id' => $deptData['user_id'] ?? $existingDept->user_id,
                            // لا نحدث الاسم لأنه هو نفسه
                        ]);
                    } else {
                        // 3. إذا لم يوجد: نقوم بإنشائه
                        $branch->departments()->create([
                            'name'       => $deptName,
                            'user_id'    => $deptData['user_id'] ?? null,
                            'created_by' => auth()->id(),
                        ]);
                    }
                    // --- [انتهى الحل] ---
                }
            }
        }

        Toaster::success(__('Created successfully'));
        $this->redirect(route('companies.index'));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.company.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Companies List'), 'url' => route('companies.index')],
                ['title' => __('Add Company'), 'url' => route('companies.add')],
            ]
        ]);
    }
}

<?php

namespace Modules\PPUDS\Livewire\Pages\Company;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
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
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
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

class Add extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

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
                    // --- الخطوة الأولى: معلومات الشركة ---
                    Wizard\Step::make(__('General Profile'))
                        ->label(__('Company Profile'))
                        ->icon('solar-buildings-3-bold-duotone') // Solar Icon
                        ->description(__('Basic identity and categorization'))
                        ->schema([
                            Grid::make(['default' => 1, 'lg' => 3])
                                ->schema([
                                    // العمود الأيمن (الرئيسي)
                                    Group::make()
                                        ->columnSpan(['lg' => 2])
                                        ->schema([
                                            Section::make(__('Company Identity'))
                                                ->icon('solar-document-text-bold-duotone') // Solar Icon
                                                ->schema([
                                                    Grid::make(2)->schema([
                                                        TextInput::make('name')
                                                            ->label(__('Company Name'))
                                                            ->required()
                                                            ->prefixIcon('solar-pen-new-square-linear') // Solar Icon
                                                            ->placeholder(__('e.g. Acme Corporation'))
                                                            ->live(debounce: 500)
                                                            ->datalist(fn () => Company::get()->pluck('name'))
                                                            ->columnSpan(1),
                                                        Placeholder::make('company_suggestions')
                                                            ->label(__('Suggestions & Similar Companies'))
                                                            ->hidden(fn (Get $get) => blank($get('name'))) // إخفاء الحقل إذا كان الاسم فارغاً
                                                            ->content(function (Get $get) {
                                                                $search = $get('name');

                                                                // جلب الشركات التي تحتوي على نفس النص
                                                                $similarCompanies = Company::whereTranslationLike('name', "%{$search}%")
                                                                    ->limit(5)
                                                                    ->get();

                                                                // 1. حالة عدم وجود شركات مشابهة (الاسم متاح)
                                                                if ($similarCompanies->isEmpty()) {
                                                                    return new HtmlString('
                <div class="p-3 rounded-lg bg-success-50 dark:bg-success-500/10 text-success-600 dark:text-success-400 border border-success-200 dark:border-success-500/20">
                    <span class="flex items-center gap-2 text-sm font-medium">
                        <x-icon name="solar-check-circle-bold" class="w-100" />
                        هذا الاسم متاح ولا يوجد شركات مشابهة في النظام.
                    </span>
                </div>
            ');
                                                                }

                                                                $categoryLabel = __('Category');
                                                                $statusLabel = __('Status');

                                                                $html = '<div class="flex flex-col gap-3 mt-1">';
                                                                $html .= '  <span class="text-sm font-medium text-warning-600 dark:text-warning-400 flex items-center gap-2">
                        <x-icon name="solar-info-circle-bold" class="w-100" />
                        انتبه، وجدنا شركات بأسماء مشابهة:
                    </span>';
                                                                $html .= '  <div class="grid gap-2">';

                                                                foreach ($similarCompanies as $company) {
                                                                    $categoryName = $company->category?->name ?? '-';

                                                                    $statusName = $company->status?->getLabel() ?? $company->status->value;

                                                                    $html .= '
                <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <strong class="text-sm text-gray-900 dark:text-white">'.$company->name.'</strong>
                        <div class="text-xs text-gray-500 mt-1">
                            '.$categoryLabel.': '.$categoryName.'
                        </div>
                    </div>
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-500/20">
                        '.$statusLabel.': '.$statusName.'
                    </span>
                </div>
            ';
                                                                }

                                                                $html .= '  </div>';
                                                                $html .= '</div>';

                                                                return new HtmlString($html);
                                                            })
                                                            ->columnSpan(1),
                                                        TextInput::make('website')
                                                            ->label(__('Website URL'))
                                                            ->url()
                                                            ->prefixIcon('solar-global-linear') // Solar Icon
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
                                                ->icon('solar-gallery-check-bold-duotone') // Solar Icon
                                                ->schema([
                                                    SpatieMediaLibraryFileUpload::make('logo')
                                                        ->label(__('Company Logo'))
                                                        ->collection('logo')
                                                        ->disk('companies')
                                                        ->image()
                                                        ->imageEditor()
                                                        ->circleCropper()
                                                        ->columnSpanFull(),

                                                    Select::make('company_category_id')
                                                        ->label(__('Category'))
                                                        ->prefixIcon('solar-tag-price-linear') // Solar Icon
                                                        ->required()
                                                        ->options(CompanyCategory::get()->pluck('name', 'id'))
                                                        ->searchable()
                                                        ->preload(),

                                                    Select::make('status')
                                                        ->label(__('Status'))
                                                        ->required()
                                                        ->prefixIcon('solar-power-linear') // Solar Icon
                                                        ->default(CompanyStatus::ACTIVE->value)
                                                        ->options(CompanyStatus::options())
                                                        ->native(false),
                                                ]),
                                        ]),
                                ]),
                        ]),

                    // --- الخطوة الثانية: الفروع والأقسام ---
                    Wizard\Step::make(__('Structure'))
                        ->label(__('Branches & Operations'))
                        ->icon('solar-shop-2-bold-duotone') // Solar Icon
                        ->description(__('Manage branches, locations, and departments'))
                        ->schema([
                            Repeater::make('branches')
                                ->label(__('Branches'))
                                ->relationship('branches')
                                ->minItems(1)
                                ->defaultItems(1)
                                ->collapsible()
                                ->cloneable()
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? __('New Branch'))
                                ->addActionLabel(__('Add New Branch'))
                                ->grid(1)
                                ->extraAttributes([
                                    'class' => 'gap-6', // مسافة بين الفروع
                                ])
                                ->schema([
                                    // هنا نضع حدوداً للفرع لتمييزه بصرياً
                                    Group::make()
                                        ->schema([
                                            Tabs::make('Branch Settings')
                                                ->tabs([
                                                    // 1. تبويب المعلومات
                                                    Tabs\Tab::make(__('Overview'))
                                                        ->icon('solar-info-circle-bold-duotone') // Solar Icon
                                                        ->schema([
                                                            Grid::make(2)->schema([
                                                                TextInput::make('name')
                                                                    ->label(__('Branch Name'))
                                                                    ->required()
                                                                    ->default(__('Main Branch'))
                                                                    ->prefixIcon('solar-shop-linear') // Solar Icon
                                                                    ->columnSpanFull(),

                                                                TextInput::make('email')
                                                                    ->label(__('Contact Email'))
                                                                    ->email()
                                                                    ->prefixIcon('solar-letter-linear'), // Solar Icon

                                                                TextInput::make('phone')
                                                                    ->label(__('Phone Number'))
                                                                    ->tel()
                                                                    ->prefixIcon('solar-phone-calling-linear'), // Solar Icon

                                                                // استبدل الـ Section القديم بهذا الكود الجديد
                                                                Section::make(__('Working Hours'))
                                                                    ->icon('solar-clock-circle-bold-duotone')
                                                                    ->schema([
                                                                        Repeater::make('working_hours')
                                                                            ->label(__('Weekly Schedule'))
                                                                            ->hiddenLabel() // إخفاء العنوان لتوفير المساحة
                                                                            ->relationship('workingHours') // اسم العلاقة في مودل Branch
                                                                            ->schema([
                                                                                Grid::make(4)->schema([
                                                                                    // 1. اسم اليوم (للعرض فقط)
                                                                                    // نستخدم Select disabled لعرض اسم اليوم بناءً على الـ Enum
                                                                                    Select::make('day')
                                                                                        ->label(__('Day'))
                                                                                        ->options(\Modules\Branch\Enums\WeekDay::class)
                                                                                        ->disabled()
                                                                                        ->dehydrated() // يرسل القيمة عند الحفظ
                                                                                        ->required()
                                                                                        ->columnSpan(1),

                                                                                    // 2. زر مغلق
                                                                                    \Filament\Forms\Components\Toggle::make('is_closed')
                                                                                        ->label(__('Closed?'))
                                                                                        ->onColor('danger')
                                                                                        ->offColor('success')
                                                                                        ->inline(false)
                                                                                        ->live() // لتحديث الحقول المجاورة فوراً
                                                                                        ->columnSpan(1),

                                                                                    // 3. وقت البداية والنهاية (يختفيان إذا كان مغلقاً)
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
                                                                            ->addable(false)      // منع إضافة أيام يدوياً
                                                                            ->deletable(false)    // منع حذف الأيام
                                                                            ->reorderable(false)  // منع تغيير الترتيب
                                                                            ->defaultItems(7)     // عرض 7 أيام دائماً
                                                                            // دالة لملء الأيام السبعة تلقائياً عند فتح الفورم الجديد
                                                                            ->default(function () {
                                                                                $days = [];
                                                                                foreach (\Modules\Branch\Enums\WeekDay::cases() as $day) {
                                                                                    $days[] = [
                                                                                        'day' => $day->value,
                                                                                        'is_closed' => $day === \Modules\Branch\Enums\WeekDay::FRIDAY, // الجمعة عطلة افتراضياً
                                                                                        'start_time' => '08:00',
                                                                                        'end_time' => '16:00',
                                                                                    ];
                                                                                }

                                                                                return $days;
                                                                            }),
                                                                    ])
                                                                    ->columnSpanFull()
                                                                    ->extraAttributes(['class' => 'bg-gray-50/50']),
                                                            ]),
                                                        ]),

                                                    // 2. تبويب الموقع
                                                    Tabs\Tab::make(__('Location'))
                                                        ->icon('solar-map-point-bold-duotone') // Solar Icon
                                                        ->schema([
                                                            Grid::make(2)->schema([
                                                                Select::make('country_id')
                                                                    ->label(__('Country'))
                                                                    ->options(Country::all()->pluck('name', 'id'))
                                                                    ->default(fn () => Country::whereTranslation('name', 'فلسطين')->orWhereTranslation('name', 'Palestine')->first()?->id)
                                                                    ->searchable()
                                                                    ->required()
                                                                    ->live()
                                                                    ->prefixIcon('solar-flag-linear') // Solar Icon
                                                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),

                                                                Select::make('city_id')
                                                                    ->label(__('City'))
                                                                    ->options(function (Get $get) {
                                                                        $countryId = $get('country_id');
                                                                        if (! $countryId) {
                                                                            return [];
                                                                        }

                                                                        return City::whereHas('governorate', function (Builder $query) use ($countryId) {
                                                                            $query->where('country_id', $countryId);
                                                                        })->get()->pluck('name', 'id');
                                                                    })
                                                                    ->default(fn () => City::whereTranslation('name', 'الخليل')->orWhereTranslation('name', 'Hebron')->first()?->id)
                                                                    ->searchable()
                                                                    ->prefixIcon('solar-city-linear') // Solar Icon
                                                                    ->required(),

                                                                TextInput::make('latitude')->numeric()->placeholder('31.xxxx')
                                                                    ->prefixIcon('solar-map-arrow-up-linear'),
                                                                TextInput::make('longitude')->numeric()->placeholder('35.xxxx')
                                                                    ->prefixIcon('solar-map-arrow-right-linear'),
                                                            ]),
                                                        ]),

                                                    // 3. تبويب الأقسام
                                                    Tabs\Tab::make(__('Departments & Staff'))
                                                        ->icon('solar-users-group-rounded-bold-duotone') // Solar Icon
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
                                                                            ->prefixIcon('solar-case-minimalistic-linear') // Solar Icon
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
                                                                            ->createOptionUsing(function (array $data) {
                                                                                return $data['new_department_name'];
                                                                            }),

                                                                        Select::make('user_id')
                                                                            ->label(__('Supervisor'))
                                                                            ->required()
                                                                            ->searchable()
                                                                            ->preload()
                                                                            ->prefixIcon('solar-user-id-linear') // Solar Icon
                                                                            ->options(fn () => User::role('Company Supervisor')->pluck('name', 'id'))
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
                                                                                ]),
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
                                                                ->extraAttributes(['class' => 'border-l-4 border-primary-500 pl-4']), // تمييز بصري لقائمة الأقسام
                                                        ]),
                                                ]),
                                        ]),
                                ]),
                        ]),
                ])
                    ->columnSpan('full')

                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::button
                            wire:click="save"
                            type="button"
                            icon="solar-diskette-bold"
                        >
                            {{ __('Save') }}
                        </x-filament::button>
                    BLADE))),
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
        $this->authorize('Company Create');

        $this->validate();

        // 1. فصل بيانات الشركة الأساسية عن الفروع والشعار
        $companyData = Arr::except($this->data, ['branches', 'logo']);
        $companyData['created_by'] = auth()->id();

        // 2. إنشاء الشركة
        $company = Company::create($companyData);

        // 3. رفع الشعار
        if (isset($this->data['logo'])) {
            $company->addImage($this->data['logo']);
        }

        // 4. معالجة الفروع
        if (! empty($this->data['branches'])) {

            foreach ($this->data['branches'] as $branchData) {

                // فصل بيانات الأقسام وساعات العمل عن بيانات الفرع
                $departmentsData = $branchData['departments'] ?? [];
                $workingHoursData = $branchData['working_hours'] ?? []; // المصفوفة الجديدة لساعات العمل

                // تنظيف بيانات الفرع
                $branchCleanData = Arr::except($branchData, ['departments', 'working_hours']);
                $branchCleanData['created_by'] = auth()->id();

                // إنشاء الفرع
                $branch = Branch::create($branchCleanData);

                // ربط الفرع بالشركة
                $company->branches()->attach($branch->id, ['is_main' => false]);

                // --- أ. حفظ ساعات العمل (الجديد) ---
                if (! empty($workingHoursData)) {
                    foreach ($workingHoursData as $wh) {
                        $branch->workingHours()->create([
                            'day' => $wh['day'],
                            'is_closed' => $wh['is_closed'],
                            'start_time' => $wh['is_closed'] ? null : $wh['start_time'],
                            'end_time' => $wh['is_closed'] ? null : $wh['end_time'],
                        ]);
                    }
                }

                // --- ب. حفظ الأقسام ---
                foreach ($departmentsData as $deptData) {
                    $deptName = $deptData['name'];
                    $supervisorId = $deptData['user_id'] ?? null;

                    // البحث عن القسم أو إنشاؤه
                    $department = CompanyDepartment::whereTranslation('name', $deptName)->first();

                    if (! $department) {
                        $department = CompanyDepartment::create([
                            'name' => $deptName,
                            'created_by' => auth()->id(),
                        ]);
                    }

                    // ربط القسم بالفرع مع المشرف
                    $branch->departments()->syncWithoutDetaching([
                        $department->id => [
                            'user_id' => $supervisorId,
                        ],
                    ]);
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
                ['title' => __('Companies'), 'url' => route('companies.index')],
                ['title' => __('New Company'), 'url' => route('companies.add')],
            ],
        ]);
    }
}

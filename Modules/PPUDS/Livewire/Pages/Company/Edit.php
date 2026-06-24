<?php

namespace Modules\PPUDS\Livewire\Pages\Company;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\MapPicker;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Enums\CompanyStatus;
use Modules\PPUDS\Services\PpuApiService;

// تمت الاضافة
// تمت الاضافة
// تمت الاضافة
// تمت الاضافة

class Edit extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?array $data = [];

    public array $initialSupervisorIds = [];

    public Company $company;

    public function mount(Company $company)
    {
        $this->company = $company;

        // 1. تعبئة البيانات الأساسية للشركة
        $formData = $company->toArray();

        // 2. تعبئة الفروع مع ساعات العمل والأقسام
        $formData['branches'] = $company->branches->map(function ($branch) {

            // --- منطق جلب ساعات العمل ---
            $existingHours = $branch->workingHours; // تأكد أن العلاقة workingHours معرفة في مودل Branch

            if ($existingHours->isEmpty()) {
                // إذا لم تكن هناك ساعات مسجلة، نضع الافتراضي (7 أيام)
                $workingHoursData = [];
                foreach (\Modules\Branch\Enums\WeekDay::cases() as $day) {
                    $workingHoursData[] = [
                        'day' => $day->value,
                        'is_closed' => $day === \Modules\Branch\Enums\WeekDay::FRIDAY, // الجمعة عطلة افتراضياً
                        'start_time' => '08:00',
                        'end_time' => '16:00',
                    ];
                }
            } else {
                // إذا كانت موجودة، نقوم بتحويلها للصيغة المناسبة للفورم
                $workingHoursData = $existingHours->map(function ($wh) {
                    return [
                        'id' => $wh->id, // مهم للتحديث لاحقاً
                        'day' => $wh->day->value, // (value) لأننا نستخدم Enum
                        'is_closed' => (bool) $wh->is_closed,
                        // تنسيق الوقت مهم جداً ليقبله الـ TimePicker
                        'start_time' => $wh->start_time ? \Carbon\Carbon::parse($wh->start_time)->format('H:i') : null,
                        'end_time' => $wh->end_time ? \Carbon\Carbon::parse($wh->end_time)->format('H:i') : null,
                    ];
                })->toArray();
            }
            // -----------------------------

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'email' => $branch->email,
                'phone' => $branch->phone,
                'country_id' => $branch->country_id,
                'city_id' => $branch->city_id,
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'location' => [
                    'lat' => (float) ($branch->latitude ?: 31.5326),
                    'lng' => (float) ($branch->longitude ?: 35.0998),
                ],

                // نضع المصفوفة المجهزة هنا
                'working_hours' => $workingHoursData,

                // جلب الأقسام
                'departments' => $branch->departments
                    ->unique(fn (CompanyDepartment $dept): int => $dept->id)
                    ->map(function (CompanyDepartment $dept) {
                        return [
                            'name' => $dept->name,
                            'user_id' => $dept->pivot->user_id,
                        ];
                    })->toArray(),
            ];
        })->toArray();

        $this->initialSupervisorIds = $this->supervisorIdsFromBranches($formData['branches']);

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
                                                        ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : $state)
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
                                ->extraAttributes(['class' => 'gap-6 company-structure-repeater'])
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
                                                                        Repeater::make('working_hours')
                                                                            ->label(__('Weekly Schedule'))
                                                                            ->hiddenLabel()
                                                                            // ->relationship('workingHours')  <--- احذف هذا السطر في Edit.php
                                                                            ->schema([
                                                                                Grid::make(4)->schema([
                                                                                    Select::make('day')
                                                                                        ->label(__('Day'))
                                                                                        ->options(\Modules\Branch\Enums\WeekDay::class)
                                                                                        ->disabled()
                                                                                        ->dehydrated()
                                                                                        ->required()
                                                                                        ->columnSpan(1),

                                                                                    \Filament\Forms\Components\Toggle::make('is_closed')
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
                                                                            ->addable(false)
                                                                            ->deletable(false)
                                                                            ->reorderable(false)
                                                                            ->defaultItems(7)
                                                                            ->default(function () { // دالة الـ Default مهمة للفروع الجديدة التي تضاف أثناء التعديل
                                                                                $days = [];
                                                                                foreach (\Modules\Branch\Enums\WeekDay::cases() as $day) {
                                                                                    $days[] = [
                                                                                        'day' => $day->value,
                                                                                        'is_closed' => $day === \Modules\Branch\Enums\WeekDay::FRIDAY,
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
                                                                        if (! $countryId) {
                                                                            return [];
                                                                        }

                                                                        return City::whereHas('governorate', function (Builder $query) use ($countryId) {
                                                                            $query->where('country_id', $countryId);
                                                                        })->get()->pluck('name', 'id');
                                                                    })
                                                                    ->searchable()
                                                                    ->prefixIcon('solar-city-linear')
                                                                    ->required(),

                                                                MapPicker::make('location')
                                                                    ->default(fn (Get $get): array => [
                                                                        'lat' => (float) ($get('latitude') ?: 31.5326),
                                                                        'lng' => (float) ($get('longitude') ?: 35.0998),
                                                                    ])
                                                                    ->defaultLocation(latitude: 31.5326, longitude: 35.0998)
                                                                    ->clickable(true)
                                                                    ->zoom(13)
                                                                    ->dehydrated(false),

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
                                                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
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
                                                                            ->createOptionUsing(fn (array $data) => $data['new_department_name']),

                                                                        Select::make('user_id')
                                                                            ->label(__('Supervisor'))
                                                                            ->required()
                                                                            ->searchable()
                                                                            ->preload()
                                                                            ->position('top')
                                                                            ->prefixIcon('solar-user-id-linear')
                                                                            ->extraAttributes(['class' => 'company-supervisor-select'])
                                                                            ->extraAlpineAttributes(['class' => 'company-supervisor-choices'])
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
                                                                                    TextInput::make('email')
                                                                                        ->required()
                                                                                        ->email()
                                                                                        ->unique('users', 'email')
                                                                                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtolower(trim($state)) : null)
                                                                                        ->validationMessages([
                                                                                            'unique' => __('This email is already taken'),
                                                                                        ]),
                                                                                    TextInput::make('phone')->required()->numeric(),
                                                                                    TextInput::make('password')->required()->password()->confirmed(),
                                                                                    TextInput::make('password_confirmation')->required()->password(),
                                                                                ]),
                                                                            ])
                                                                            ->createOptionUsing(function (array $data) {
                                                                                if (User::where('email', $data['email'])->exists()) {
                                                                                    throw ValidationException::withMessages([
                                                                                        'email' => __('This email is already taken'),
                                                                                    ]);
                                                                                }

                                                                                $plainPassword = $data['password'];
                                                                                $data['password'] = bcrypt($data['password']);
                                                                                $user = User::create($data);
                                                                                $user->assignRole('Company Supervisor');
                                                                                session()->put($this->supervisorPasswordSessionKey($user->id), $plainPassword);

                                                                                return $user->id;
                                                                            })
                                                                            ->required(),
                                                                    ]),
                                                                ])
                                                                ->defaultItems(0)
                                                                ->collapsible()
                                                                ->itemLabel(__('Department Assignment'))
                                                                ->addActionLabel(__('Add Department'))
                                                                ->reorderableWithButtons()
                                                                ->extraAttributes(['class' => 'company-departments-repeater border-l-4 border-primary-500 pl-4']),
                                                        ]),
                                                ]),
                                        ]),
                                ]),
                        ]),
                ])
                    ->columnSpan('full')
                    // زر الحفظ يظهر فقط في الخطوة الأخيرة
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                    <x-filament::button
                        wire:click="save"
                        type="button"
                        icon="solar-diskette-bold"
                        color="success"
                    >
                        {{ __('Update') }}
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
        // 1. التحقق من الصلاحيات والبيانات
        $this->authorize('Company Update');
        $this->validate();

        // 2. تحديث بيانات الشركة الأساسية (مع استبعاد الفروع والشعار)
        $companyData = Arr::except($this->data, ['branches', 'logo']);
        $companyData['description'] = blank($companyData['description'] ?? null) ? null : $companyData['description'];
        $this->company->update($companyData);

        // 3. حفظ الصور (الشعار) عبر Filament Spatie Plugin
        $this->form->model($this->company)->saveRelationships();

        // 4. دالة مخصصة لحفظ الفروع، الأقسام، وساعات العمل
        $this->saveBranchesAndDepartments();

        // 5. إرسال المشرفين الجدد إلى نظام الجامعة عبر Company/Add
        $this->syncAddedSupervisorsToUniversity();

        // 6. رسالة نجاح وتوجيه
        Toaster::success(__('Company updated successfully'));
        $this->redirect(route('companies.index'));
    }

    private function syncAddedSupervisorsToUniversity(): void
    {
        $addedSupervisorIds = array_values(array_diff(
            $this->selectedCompanySupervisorIds(),
            $this->initialSupervisorIds,
        ));

        if ($addedSupervisorIds === []) {
            return;
        }

        $company = $this->company->fresh(['branches.supervisors', 'translations']);

        if (! $company) {
            return;
        }

        $apiService = app(PpuApiService::class);
        $created = 0;
        $alreadyExists = 0;

        foreach ($this->prioritizeSupervisorIdsForSync($addedSupervisorIds) as $supervisorId) {
            $password = session()->pull($this->supervisorPasswordSessionKey($supervisorId));
            $result = $apiService->addCompanyToUniversity(
                $company,
                $password,
                $supervisorId,
                sendEvenIfCompanyExists: true,
            );

            if (($result['operation'] ?? null) === 'already_exists') {
                $alreadyExists++;
            } elseif ($result !== null) {
                $created++;
            }
        }

        if ($created > 0) {
            Toaster::success(count($addedSupervisorIds) > 1
                ? __('Company supervisors sent to university successfully')
                : __('Company supervisor sent to university successfully'));

            return;
        }

        if ($alreadyExists > 0) {
            Toaster::success(__('Company supervisor already exists in university system'));
        }
    }

    protected function saveBranchesAndDepartments()
    {
        $formBranches = $this->data['branches'] ?? [];
        $processedBranchIds = [];

        foreach ($formBranches as $branchData) {

            $branchId = $branchData['id'] ?? null;

            // استخراج المصفوفات الفرعية
            $departmentsData = $branchData['departments'] ?? [];
            $workingHoursData = $branchData['working_hours'] ?? []; // المصفوفة اليدوية لساعات العمل

            // تنظيف بيانات الفرع (إبقاء الحقول الأساسية فقط)
            $branchAttributes = Arr::except($branchData, ['departments', 'working_hours', 'id', 'location']);

            $branch = null;

            // --- أ. التعامل مع الفرع (تحديث أو إنشاء) ---
            if ($branchId) {
                // تحديث فرع موجود
                $branch = Branch::find($branchId);
                if ($branch) {
                    $branch->update($branchAttributes);
                }
            } else {
                // إنشاء فرع جديد (أضيف أثناء التعديل)
                $branchAttributes['created_by'] = auth()->id();
                $branch = Branch::create($branchAttributes);
                $this->company->branches()->attach($branch->id, ['is_main' => false]);
            }

            if ($branch) {
                $processedBranchIds[] = $branch->id;

                // --- ب. حفظ الأقسام (باستخدام الدالة الموجودة مسبقاً) ---
                $this->syncDepartmentsForBranch($branch, $departmentsData);

                // --- ج. حفظ ساعات العمل (الجزء الجديد والمهم) ---
                // نستخدم updateOrCreate لنضمن تحديث اليوم إذا كان موجوداً أو إنشاؤه إذا كان جديداً
                foreach ($workingHoursData as $wh) {
                    $branch->workingHours()->updateOrCreate(
                        ['day' => $wh['day']], // مفتاح البحث: اليوم
                        [
                            'is_closed' => $wh['is_closed'],
                            // إذا كان مغلقاً، نصفر الأوقات، وإلا نحفظ الوقت القادم من الفورم
                            'start_time' => $wh['is_closed'] ? null : $wh['start_time'],
                            'end_time' => $wh['is_closed'] ? null : $wh['end_time'],
                        ]
                    );
                }
            }
        }

        // --- د. حذف الفروع التي أزالها المستخدم من الـ Repeater ---
        $currentCompanyBranchIds = $this->company->branches()->pluck('branch_branches.id')->toArray(); // تأكد من اسم الجدول الوسيط الصحيح

        // أو يمكنك استخدام العلاقة المباشرة إذا كانت معرفة:
        // $currentCompanyBranchIds = $this->company->branches->pluck('id')->toArray();

        $branchesToDetach = array_diff($currentCompanyBranchIds, $processedBranchIds);

        if (! empty($branchesToDetach)) {
            // فصل الفروع المحذوفة عن الشركة
            $this->company->branches()->detach($branchesToDetach);

            // خياري: إذا أردت حذف الفرع نهائياً من قاعدة البيانات
            // Branch::destroy($branchesToDetach);
        }
    }

    protected function syncDepartmentsForBranch(Branch $branch, array $departmentsData): void
    {
        $pivotTable = config('ppuds.table_prefix').'branch_department';
        $rows = $this->departmentPivotRows($branch, $departmentsData);

        DB::transaction(function () use ($pivotTable, $branch, $rows): void {
            DB::table($pivotTable)
                ->where('branch_id', $branch->id)
                ->delete();

            if ($rows !== []) {
                DB::table($pivotTable)->insert($rows);
            }
        });
    }

    private function departmentPivotRows(Branch $branch, array $departmentsData): array
    {
        $now = now();

        return collect($this->normalizeDepartmentsData($departmentsData))
            ->map(function (array $deptData) use ($branch, $now): array {
                $department = $this->resolveCompanyDepartment($deptData['name']);

                return [
                    'branch_id' => $branch->id,
                    'company_department_id' => $department->id,
                    'user_id' => $deptData['user_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();
    }

    private function normalizeDepartmentsData(array $departmentsData): array
    {
        return collect($departmentsData)
            ->filter(fn (mixed $deptData): bool => is_array($deptData)
                && filled($deptData['name'] ?? null)
                && filled($deptData['user_id'] ?? null))
            ->map(fn (array $deptData): array => [
                'name' => trim((string) $deptData['name']),
                'user_id' => (int) $deptData['user_id'],
            ])
            ->unique(fn (array $deptData): string => mb_strtolower($deptData['name']))
            ->values()
            ->all();
    }

    private function resolveCompanyDepartment(string $name): CompanyDepartment
    {
        $department = CompanyDepartment::whereTranslation('name', $name)->first();

        if ($department) {
            return $department;
        }

        return CompanyDepartment::create([
            'name' => $name,
            'created_by' => auth()->id(),
        ]);
    }

    private function selectedCompanySupervisorIds(): array
    {
        return $this->supervisorIdsFromBranches($this->data['branches'] ?? []);
    }

    private function supervisorIdsFromBranches(array $branches): array
    {
        return collect($branches)
            ->flatMap(fn (array $branch): array => $branch['departments'] ?? [])
            ->pluck('user_id')
            ->filter(fn (mixed $supervisorId): bool => filled($supervisorId))
            ->map(fn (mixed $supervisorId): int => (int) $supervisorId)
            ->unique()
            ->values()
            ->all();
    }

    private function prioritizeSupervisorIdsForSync(array $supervisorIds): array
    {
        return collect($supervisorIds)
            ->map(fn (int $supervisorId): array => [
                'id' => $supervisorId,
                'has_password' => session()->has($this->supervisorPasswordSessionKey($supervisorId)),
            ])
            ->sortByDesc('has_password')
            ->pluck('id')
            ->values()
            ->all();
    }

    private function supervisorPasswordSessionKey(int $supervisorId): string
    {
        return "company_supervisor_plain_password_{$supervisorId}";
    }

    public function render()
    {
        return view('ppuds::livewire.pages.company.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Companies List'), 'url' => route('companies.index')],
                ['title' => __('Edit Company'), 'url' => route('companies.edit', $this->company->id)],
            ],
        ]);
    }
}

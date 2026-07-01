<?php

namespace Modules\PPUDS\Livewire\Pages\Company;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\MapPicker;
use Modules\Core\Services\CompanyProfileGeneratorService;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\CompanyTranslation;
use Modules\PPUDS\Enums\CompanyStatus;
use Modules\PPUDS\Services\PpuApiService;

class Add extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?array $data = [];

    public array $createdCompanySupervisorIds = [];

    public array $pendingCreatedSupervisorAssignments = [];

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
                    Wizard\Step::make(__('General Profile'))
                        ->label(__('Company Profile'))
                        ->icon('solar-buildings-3-bold-duotone')
                        ->description(__('Basic identity and categorization'))
                        ->schema([
                            Actions::make([
                                FormAction::make('generateCompanyWithAi')
                                    ->label(__('Generate Company With AI'))
                                    ->icon('heroicon-o-sparkles')
                                    ->color('primary')
                                    ->modalHeading(__('Generate Company With AI'))
                                    ->modalSubmitActionLabel(__('Generate'))
                                    ->form([
                                        Textarea::make('brief')
                                            ->label(__('Company Brief'))
                                            ->required()
                                            ->rows(7)
                                            ->placeholder(__('Company name, sector, location, website, contact details, and training area')),

                                        \Filament\Forms\Components\Toggle::make('include_departments')
                                            ->label(__('Suggest Departments'))
                                            ->default(false),

                                        \Filament\Forms\Components\Toggle::make('replace_existing')
                                            ->label(__('Replace Current Form Data'))
                                            ->default(false),
                                    ])
                                    ->action(function (array $data): void {
                                        $result = app(CompanyProfileGeneratorService::class)->generate(
                                            $data['brief'],
                                            (bool) ($data['include_departments'] ?? false),
                                        );

                                        if (! $this->applyGeneratedCompanyProfile(
                                            $result['profile'] ?? [],
                                            (bool) ($data['replace_existing'] ?? false),
                                        )) {
                                            return;
                                        }

                                        Toaster::success($result['message'] ?? __('Company profile generated successfully.'));
                                    }),
                            ])
                                ->key('company_ai_actions')
                                ->fullWidth(),

                            Grid::make(['default' => 1, 'lg' => 3])
                                ->schema([
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
                                                            ->live(debounce: 500)
                                                            ->datalist(fn () => Company::get()->pluck('name'))
                                                            ->columnSpan(1)
                                                            ->unique(CompanyTranslation::class, 'name', ignoreRecord: true),
                                                        Placeholder::make('company_suggestions')
                                                            ->label(__('Suggestions & Similar Companies'))
                                                            ->hidden(fn (Get $get) => blank($get('name')))
                                                            ->content(function (Get $get) {
                                                                $search = $get('name');

                                                                $similarCompanies = Company::whereTranslationLike('name', "%{$search}%")
                                                                    ->limit(5)
                                                                    ->get();

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
                                ->minItems(1)
                                ->defaultItems(1)
                                ->collapsible()
                                ->cloneable()
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? __('New Branch'))
                                ->addActionLabel(__('Add New Branch'))
                                ->grid(1)
                                ->extraAttributes([
                                    'class' => 'gap-6 company-structure-repeater', // مسافة بين الفروع
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

                                                                MapPicker::make('location')
                                                                    ->default(['lat' => 31.5326, 'lng' => 35.0998])
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
                                                                            ->createOptionUsing(function (array $data) {
                                                                                return $data['new_department_name'];
                                                                            }),

                                                                        Select::make('user_id')
                                                                            ->label(__('Supervisor'))
                                                                            ->required()
                                                                            ->searchable()
                                                                            ->preload()
                                                                            ->position('top')
                                                                            ->prefixIcon('solar-user-id-linear') // Solar Icon
                                                                            ->extraAttributes(['class' => 'company-supervisor-select'])
                                                                            ->extraAlpineAttributes(['class' => 'company-supervisor-choices'])
                                                                            ->options(fn () => User::role('Company Supervisor')->pluck('name', 'id'))
                                                                            ->getSearchResultsUsing(
                                                                                fn (string $search) => User::role('Company Supervisor')
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
                                                                            ->createOptionUsing(function (array $data, Get $get, Set $set) {
                                                                                if (User::where('email', $data['email'])->exists()) {
                                                                                    throw ValidationException::withMessages([
                                                                                        'email' => __('This email is already taken'),
                                                                                    ]);
                                                                                }

                                                                                $plainPassword = $data['password'];
                                                                                $data['password'] = bcrypt($data['password']);
                                                                                $user = User::create($data);
                                                                                $user->assignRole('Company Supervisor');

                                                                                $supervisorId = (int) $user->getKey();
                                                                                $this->rememberCreatedCompanySupervisor($supervisorId, $plainPassword);
                                                                                $this->rememberPendingCreatedSupervisorAssignment($get, $supervisorId);
                                                                                $set('user_id', (string) $supervisorId);

                                                                                return (string) $supervisorId;
                                                                            })
                                                                            ->required(),
                                                                    ]),
                                                                ])
                                                                ->defaultItems(0)
                                                                ->collapsible()
                                                                ->itemLabel(__('Department Assignment'))
                                                                ->addActionLabel(__('Add Department'))
                                                                ->reorderableWithButtons()
                                                                ->extraAttributes(['class' => 'company-departments-repeater border-l-4 border-primary-500 pl-4']), // تمييز بصري لقائمة الأقسام
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

    private function applyGeneratedCompanyProfile(array $profile, bool $replaceExisting): bool
    {
        if ($profile === []) {
            Toaster::error(__('Unable to generate company profile.'));

            return false;
        }

        $mergedData = $this->mergeGeneratedCompanyData($this->data ?? [], $profile, $replaceExisting);

        $this->data = $mergedData;
        $this->form->fill($mergedData);

        return true;
    }

    private function mergeGeneratedCompanyData(array $currentData, array $profile, bool $replaceExisting): array
    {
        $mergedData = $currentData;

        foreach (['name', 'website', 'description', 'company_category_id', 'status'] as $field) {
            $mergedData[$field] = $this->mergedValue(
                $currentData[$field] ?? null,
                $profile[$field] ?? null,
                $replaceExisting,
            );
        }

        $mergedData['branches'] = $this->mergeGeneratedBranches(
            $currentData['branches'] ?? [],
            $profile['branches'] ?? [],
            $replaceExisting,
        );

        return $mergedData;
    }

    private function mergeGeneratedBranches(array $currentBranches, array $generatedBranches, bool $replaceExisting): array
    {
        $generatedBranches = array_values(array_filter($generatedBranches));

        if ($generatedBranches === []) {
            return $currentBranches;
        }

        if ($replaceExisting || $currentBranches === []) {
            return $generatedBranches;
        }

        $currentBranches = array_values($currentBranches);
        $currentBranches[0] = $this->mergeGeneratedBranch(
            $currentBranches[0] ?? [],
            $generatedBranches[0],
            $replaceExisting,
        );

        return $currentBranches;
    }

    private function mergeGeneratedBranch(array $currentBranch, array $generatedBranch, bool $replaceExisting): array
    {
        foreach (['name', 'email', 'phone'] as $field) {
            $currentBranch[$field] = $this->mergedValue(
                $currentBranch[$field] ?? null,
                $generatedBranch[$field] ?? null,
                $replaceExisting || $this->isDefaultBranchName($field, $currentBranch[$field] ?? null),
            );
        }

        foreach (['country_id', 'city_id', 'latitude', 'longitude', 'location'] as $field) {
            $currentBranch[$field] = filled($generatedBranch[$field] ?? null)
                ? $generatedBranch[$field]
                : ($currentBranch[$field] ?? null);
        }

        if ($replaceExisting || empty($currentBranch['working_hours'] ?? [])) {
            $currentBranch['working_hours'] = $generatedBranch['working_hours'] ?? [];
        }

        if ($replaceExisting || empty($currentBranch['departments'] ?? [])) {
            $currentBranch['departments'] = $generatedBranch['departments'] ?? [];
        }

        return $currentBranch;
    }

    private function mergedValue(mixed $currentValue, mixed $generatedValue, bool $replaceExisting): mixed
    {
        if (blank($generatedValue)) {
            return $currentValue;
        }

        if ($replaceExisting || blank($currentValue)) {
            return $generatedValue;
        }

        return $currentValue;
    }

    private function isDefaultBranchName(string $field, mixed $value): bool
    {
        if ($field !== 'name') {
            return false;
        }

        return in_array((string) $value, ['Main Branch', __('Main Branch')], true);
    }

    public function save()
    {
        $this->authorize('Company Create');

        $this->validate();
        $this->data = $this->form->getState();
        $this->mergePendingCreatedSupervisorAssignmentsIntoFormData();

        // 1. فصل بيانات الشركة الأساسية عن الفروع والشعار
        $companyData = Arr::except($this->data, ['branches', 'logo']);
        $companyData['description'] = blank($companyData['description'] ?? null) ? null : $companyData['description'];

        if (auth()->user()->hasRole('Student')) {
            $companyData['status'] = CompanyStatus::PENDING->value;
        }

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
                $branchCleanData = Arr::except($branchData, ['departments', 'working_hours', 'location']);
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
                $this->syncDepartmentsForBranch($branch, $departmentsData);
            }
        }

        $this->syncCompanyToUniversity($company);

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

    private function syncCompanyToUniversity(Company $company): void
    {
        $apiService = app(PpuApiService::class);
        $supervisors = $this->companySupervisorSyncCandidates();

        if ($supervisors === []) {
            $result = $apiService->addCompanyToUniversity($company);

            $this->toastUniversitySyncResult($result);

            return;
        }

        $created = 0;
        $alreadyExists = 0;
        $failed = 0;

        foreach ($supervisors as $index => $supervisor) {
            $password = session()->pull($this->supervisorPasswordSessionKey($supervisor['id']));
            $result = $apiService->addCompanyToUniversity(
                $company->refresh(),
                $password,
                $supervisor['id'],
                sendEvenIfCompanyExists: $index > 0 || ($supervisor['was_created'] ?? false),
            );

            if (($result['operation'] ?? null) === 'already_exists') {
                $alreadyExists++;
            } elseif (($result['success'] ?? false) === true) {
                $created++;
            } else {
                $failed++;
            }
        }

        if ($created > 0) {
            Toaster::success(count($supervisors) > 1
                ? __('Company supervisors sent to university successfully')
                : __('Company supervisor sent to university successfully'));

            return;
        }

        if ($alreadyExists > 0) {
            Toaster::success(__('Company supervisor already exists in university system'));
        }

        if ($failed > 0) {
            Toaster::error(__('Unable to send company supervisor to university system'));
        }
    }

    private function toastUniversitySyncResult(?array $result): void
    {
        if ($result === null) {
            return;
        }

        if (($result['operation'] ?? null) === 'already_exists') {
            Toaster::success(__('Company already exists in university system'));

            return;
        }

        Toaster::success(__('Company sent to university successfully'));
    }

    private function companySupervisorSyncCandidates(): array
    {
        $createdSupervisorIds = collect($this->createdCompanySupervisorIds)
            ->map(fn (mixed $supervisorId): int => (int) $supervisorId)
            ->filter()
            ->unique()
            ->values();

        return collect($this->selectedCompanySupervisorIds())
            ->map(function (int $supervisorId) use ($createdSupervisorIds): array {
                $hasPassword = session()->has($this->supervisorPasswordSessionKey($supervisorId));

                return [
                    'id' => $supervisorId,
                    'was_created' => $createdSupervisorIds->contains($supervisorId) || $hasPassword,
                    'has_password' => $hasPassword,
                ];
            })
            ->sortByDesc(
                fn (array $supervisor): int => ($supervisor['was_created'] ? 2 : 0)
                    + ($supervisor['has_password'] ? 1 : 0)
            )
            ->values()
            ->all();
    }

    private function rememberCreatedCompanySupervisor(int $supervisorId, string $plainPassword): void
    {
        session()->put($this->supervisorPasswordSessionKey($supervisorId), $plainPassword);

        $this->createdCompanySupervisorIds = collect($this->createdCompanySupervisorIds)
            ->push($supervisorId)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function rememberPendingCreatedSupervisorAssignment(Get $get, int $supervisorId): void
    {
        $departmentName = trim((string) $get('name'));

        if (blank($departmentName)) {
            return;
        }

        $this->pendingCreatedSupervisorAssignments[] = [
            'branch_name' => trim((string) $get('../../name')),
            'department_name' => $departmentName,
            'user_id' => $supervisorId,
        ];
    }

    private function mergePendingCreatedSupervisorAssignmentsIntoFormData(): void
    {
        if ($this->pendingCreatedSupervisorAssignments === []) {
            return;
        }

        foreach ($this->pendingCreatedSupervisorAssignments as $assignment) {
            foreach ($this->data['branches'] ?? [] as &$branch) {
                if (filled($assignment['branch_name'] ?? null)
                    && trim((string) ($branch['name'] ?? '')) !== $assignment['branch_name']) {
                    continue;
                }

                foreach ($branch['departments'] ?? [] as &$department) {
                    if (trim((string) ($department['name'] ?? '')) !== $assignment['department_name']) {
                        continue;
                    }

                    $department['user_id'] = (int) $assignment['user_id'];
                    continue 3;
                }

                $branch['departments'][] = [
                    'name' => $assignment['department_name'],
                    'user_id' => (int) $assignment['user_id'],
                ];

                continue 2;
            }

            unset($department, $branch);
        }
    }

    private function syncDepartmentsForBranch(Branch $branch, array $departmentsData): void
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
        return collect($this->data['branches'] ?? [])
            ->flatMap(fn (array $branch): array => $branch['departments'] ?? [])
            ->pluck('user_id')
            ->filter(fn (mixed $supervisorId): bool => filled($supervisorId))
            ->map(fn (mixed $supervisorId): int => (int) $supervisorId)
            ->unique()
            ->values()
            ->all();
    }

    private function supervisorPasswordSessionKey(int $supervisorId): string
    {
        return "company_supervisor_plain_password_{$supervisorId}";
    }
}

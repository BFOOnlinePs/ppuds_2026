<?php

namespace Modules\PPUDS\Livewire\Pages\Company\Details;

use App\View\Components\AppLayout;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\View;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\MapPicker;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Services\PpuApiService;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Details extends Component implements HasForms, HasInfolists, HasActions
{
    use InteractsWithForms;
    use InteractsWithInfolists;
    use InteractsWithActions;

    public ?array $data = [];

    public array $pendingCreatedSupervisorAssignments = [];

    public Company $company;

    protected ?array $linkedDepartmentIdsCache = null;

    public function mount(Company $company)
    {
        $this->company = $company;
        $this->company->loadMissing(['branches.workingHours', 'branches.departments']);

        // 1. تعبئة البيانات الأساسية للشركة
        $formData = $company->toArray();
        $formData['attachment_uploads'] = [];

        // 2. تعبئة الفروع مع ساعات العمل والأقسام
        $formData['branches'] = $company->branches->map(function ($branch) {

            // --- منطق جلب ساعات العمل ---
            $existingHours = $branch->workingHours;

            if ($existingHours->isEmpty()) {
                $workingHoursData = [];
                foreach (\Modules\Branch\Enums\WeekDay::cases() as $day) {
                    $workingHoursData[] = [
                        'day' => $day->value,
                        'is_closed' => $day === \Modules\Branch\Enums\WeekDay::FRIDAY,
                        'start_time' => '08:00',
                        'end_time' => '16:00',
                    ];
                }
            } else {
                $workingHoursData = $existingHours->map(function ($wh) {
                    return [
                        'id' => $wh->id,
                        'day' => $wh->day->value,
                        'is_closed' => (bool) $wh->is_closed,
                        'start_time' => $wh->start_time ? \Carbon\Carbon::parse($wh->start_time)->format('H:i') : null,
                        'end_time' => $wh->end_time ? \Carbon\Carbon::parse($wh->end_time)->format('H:i') : null,
                    ];
                })->toArray();
            }

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'email' => $branch->email,
                'phone' => $branch->phone,
                'manager_name' => $branch->manager_name,
                'manager_phone' => $branch->manager_phone,
                'country_id' => $branch->country_id,
                'city_id' => $branch->city_id,
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'location' => [
                    'lat' => (float) ($branch->latitude ?: 31.5326),
                    'lng' => (float) ($branch->longitude ?: 35.0998),
                ],
                'working_hours' => $workingHoursData,
                // مثل صفحة التعديل: القسم قد يكون له أكثر من مشرف والنموذج يحمل واحداً،
                // فنأخذ آخر من أُسند حتى لا يختفي ثم يُحذف صفه عند الحفظ.
                'departments' => $branch->departments
                    ->sortByDesc(fn (CompanyDepartment $dept): int => (int) $dept->pivot->id)
                    ->unique(fn (CompanyDepartment $dept): int => $dept->id)
                    ->map(function (CompanyDepartment $dept) {
                        return [
                            'name' => $dept->name,
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
                Grid::make(3)
                    ->schema([
                        Tabs::make('tabs')
                            ->tabs([

                                // التعديل هنا: استخدام دالة الترجمة
                                Tabs\Tab::make(__('Personal Information'))
                                    ->icon('heroicon-o-user')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('name')
                                                            ->label(__('Name'))
                                                            ->columnSpanFull()
                                                            ->required(),

                                                        TextInput::make('website')
                                                            ->label(__('Website'))
                                                            ->columnSpan(1)
                                                            ->url(),

                                                        Select::make('company_category_id')
                                                            ->label(__('Company Category'))
                                                            ->options(CompanyCategory::all()->pluck('name', 'id'))
                                                            ->required(),

                                                        Textarea::make('description')
                                                            ->label(__('Description'))
                                                            ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : $state)
                                                            ->columnSpanFull()
                                                            ->rows(3),
                                                    ])
                                                    ->columnSpan(2),

                                                Grid::make(1)
                                                    ->schema([
                                                        SpatieMediaLibraryFileUpload::make('cover_photo')
                                                            ->disk('media')
                                                            ->collection('cover_photo')
                                                            ->imageEditor()
                                                            ->alignCenter(),

                                                        SpatieMediaLibraryFileUpload::make('logo')
                                                            ->disk('media')
                                                            ->collection('logo')
                                                            ->image()
                                                            ->imageEditor()
                                                            ->avatar()
                                                            ->alignCenter(),
                                                    ])
                                                    ->columnSpan(1),
                                            ]),
                                    ]),

                                Tabs\Tab::make(__('Company Attachments'))
                                    ->icon('heroicon-o-paper-clip')
                                    ->schema([
                                        Section::make(__('Company Attachments'))
                                            ->icon('heroicon-o-paper-clip')
                                            ->schema([
                                                Placeholder::make('current_attachments')
                                                    ->label(__('Current Attachments'))
                                                    ->visible(fn (): bool => $this->company->getMedia('attachments')->isNotEmpty())
                                                    ->content(fn (): HtmlString => new HtmlString(
                                                        Blade::render(<<<'HTML'
                                                            <div class="grid gap-2">
                                                                @foreach ($attachments as $attachment)
                                                                    <div
                                                                        class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm transition hover:border-primary-300 hover:bg-primary-50/40 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-500"
                                                                    >
                                                                        <div class="min-w-0">
                                                                            <div class="truncate font-medium text-gray-800 dark:text-gray-100">
                                                                                {{ $attachment->name ?: $attachment->file_name }}
                                                                            </div>
                                                                            <div class="truncate text-xs text-gray-500">
                                                                                {{ $attachment->file_name }}
                                                                            </div>
                                                                        </div>
                                                                        <div class="flex shrink-0 items-center gap-2">
                                                                            <span class="text-xs text-gray-500">
                                                                                {{ $attachment->human_readable_size }}
                                                                            </span>
                                                                            <button
                                                                                type="button"
                                                                                wire:click="downloadAttachment({{ $attachment->id }})"
                                                                                class="rounded-md px-2 py-1 text-xs font-medium text-primary-600 transition hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-500/10"
                                                                            >
                                                                                {{ __('Download') }}
                                                                            </button>
                                                                            <button
                                                                                type="button"
                                                                                wire:click="deleteAttachment({{ $attachment->id }})"
                                                                                wire:confirm="{{ __('Are you sure you want to delete this attachment?') }}"
                                                                                class="rounded-md px-2 py-1 text-xs font-medium text-danger-600 transition hover:bg-danger-50 dark:text-danger-400 dark:hover:bg-danger-500/10"
                                                                            >
                                                                                {{ __('Delete') }}
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        HTML, [
                                                            'attachments' => $this->company->getMedia('attachments'),
                                                        ])
                                                    ))
                                                    ->columnSpanFull(),

                                                Repeater::make('attachment_uploads')
                                                    ->label(__('Add Attachment'))
                                                    ->schema([
                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('name')
                                                                    ->label(__('Attachment Name'))
                                                                    ->required()
                                                                    ->maxLength(255),

                                                                FileUpload::make('file')
                                                                    ->label(__('Attachment File'))
                                                                    ->required()
                                                                    ->storeFiles(false)
                                                                    ->preserveFilenames()
                                                                    ->maxSize(10240),
                                                            ]),
                                                    ])
                                                    ->defaultItems(1)
                                                    ->addActionLabel(__('Add Another Attachment'))
                                                    ->reorderable(false)
                                                    ->collapsible()
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),

                                // التعديل هنا: تصحيح الاسم واستخدام دالة الترجمة ليطابق ملف JSON
                                Tabs\Tab::make(__('Branches & Departments'))
                                    ->icon('solar-shop-2-bold-duotone')
                                    ->schema([
                                        Repeater::make('branches')
                                            ->label(__('Branches'))
                                            ->collapsed()
                                            ->collapsible()
                                            ->cloneable()
                                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? __('New Branch'))
                                            ->addActionLabel(__('Add New Branch'))
                                            ->deleteAction(fn (FormAction $action) => $action->visible(
                                                fn (array $arguments, Repeater $component): bool => ! $this->isBranchLinked($component->getRawItemState($arguments['item'])['id'] ?? null)
                                            ))
                                            ->grid(1)
                                            ->extraAttributes(['class' => 'gap-6 company-structure-repeater'])
                                            ->schema([
                                                // الحقل المخفي لا يصل إلى getState() بدون dehydratedWhenHidden،
                                                // فكان كل حفظ يُنشئ نسخة جديدة من كل فرع موجود.
                                                TextInput::make('id')->hidden()->dehydratedWhenHidden(),

                                                Group::make()
                                                    ->schema([
                                                        Tabs::make('Branch Settings')
                                                            ->tabs([
                                                                // 1. Overview
                                                                Tabs\Tab::make(__('Overview'))
                                                                    ->icon('solar-info-circle-bold-duotone')
                                                                    ->schema([
                                                                        Grid::make(4)
                                                                            ->schema([
                                                                                TextInput::make('name')
                                                                                    ->label(__('Branch Name'))
                                                                                    ->required()
                                                                                    ->default(__('Main Branch'))
                                                                                    ->columnSpanFull(),

                                                                                TextInput::make('email')
                                                                                    ->label(__('Contact Email'))
                                                                                    ->email(),

                                                                                TextInput::make('phone')
                                                                                    ->label(__('Phone Number')),

                                                                                TextInput::make('manager_name')
                                                                                    ->label(__('Company Manager Name'))
                                                                                    ->maxLength(255),

                                                                                TextInput::make('manager_phone')
                                                                                    ->label(__('Company Manager Phone'))
                                                                                    ->tel()
                                                                                    ->maxLength(50),

                                                                                // Working hours
                                                                                Section::make(__('Working Hours'))
                                                                                    ->icon('solar-clock-circle-bold-duotone')
                                                                                    ->schema([
                                                                                        Repeater::make('working_hours')
                                                                                            ->hiddenLabel()
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
                                                                                            ->default(function () {
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

                                                                // 2. Location
                                                                Tabs\Tab::make(__('Location'))
                                                                    ->icon('solar-map-point-bold-duotone')
                                                                    ->schema([
                                                                        Grid::make(2)
                                                                            ->schema([
                                                                                Select::make('country_id')
                                                                                    ->label(__('Country'))
                                                                                    ->options(Country::with('translations')->get()->pluck('name', 'id'))
                                                                                    // ملاحظة: تم ابقاء القيم العربية هنا لأنها قيم بحث في قاعدة البيانات
                                                                                    ->default(fn () => Country::whereTranslation('name', 'فلسطين')
                                                                                        ->orWhereTranslation('name', 'Palestine')->first()?->id)
                                                                                    ->searchable()
                                                                                    ->required()
                                                                                    ->live()
                                                                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),

                                                                                Select::make('city_id')
                                                                                    ->label(__('City'))
                                                                                    ->options(function (Get $get) {
                                                                                        $countryId = $get('country_id');
                                                                                        if (! $countryId) {
                                                                                            return [];
                                                                                        }

                                                                                        return City::with('translations')->whereHas('governorate', function (Builder $query) use ($countryId) {
                                                                                            $query->where('country_id', $countryId);
                                                                                        })->get()->pluck('name', 'id');
                                                                                    })
                                                                                    // ملاحظة: تم ابقاء القيم العربية هنا لأنها قيم بحث في قاعدة البيانات
                                                                                    ->default(fn () => City::whereTranslation('name', 'الخليل')
                                                                                        ->orWhereTranslation('name', 'Hebron')->first()?->id)
                                                                                    ->searchable()
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

                                                                                TextInput::make('latitude')->numeric()->placeholder('31.xxxx'),
                                                                                TextInput::make('longitude')->numeric()->placeholder('35.xxxx'),
                                                                            ]),
                                                                    ]),

                                                                // 3. Departments & Staff
                                                                Tabs\Tab::make(__('Departments & Staff'))
                                                                    ->icon('solar-users-group-rounded-bold-duotone')
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
                                                                                            return CompanyDepartment::with('translations')->get()
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
                                                                                                TextInput::make('name')->label(__('Name'))->required(),
                                                                                                TextInput::make('name_en')->label(__('Name (English)'))->required(),
                                                                                                TextInput::make('email')
                                                                                                    ->label(__('Email'))
                                                                                                    ->required()
                                                                                                    ->email()
                                                                                                    ->unique('users', 'email')
                                                                                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtolower(trim($state)) : null)
                                                                                                    ->validationMessages([
                                                                                                        'unique' => __('This email is already taken'),
                                                                                                    ]),
                                                                                                TextInput::make('phone')
                                                                                                    ->label(__('Phone'))
                                                                                                    ->required()
                                                                                                    ->numeric()
                                                                                                    ->unique('users', 'phone')
                                                                                                    ->validationMessages([
                                                                                                        'unique' => __('This phone number is already taken'),
                                                                                                    ]),
                                                                                                TextInput::make('password')->label(__('Password'))->required()->password()->confirmed(),
                                                                                                TextInput::make('password_confirmation')->label(__('Confirm Password'))->required()->password(),
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
                                                                                            session()->put($this->supervisorPasswordSessionKey($user->id), $plainPassword);

                                                                                            $supervisorId = (int) $user->getKey();
                                                                                            $set('user_id', (string) $supervisorId);
                                                                                            $this->attachCreatedSupervisorToCompanyDepartment($get, $supervisorId, $plainPassword);

                                                                                            return (string) $supervisorId;
                                                                                        })
                                                                                        ->required(),
                                                                                ]),
                                                                            ])
                                                                            ->defaultItems(0)
                                                                            ->collapsible()
                                                                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                                                            ->addActionLabel(__('Add Department'))
                                                                            ->deleteAction(fn (FormAction $action) => $action->visible(
                                                                                fn (array $arguments, Repeater $component, Get $get): bool => ! $this->isDepartmentLinked($get('id'), $component->getRawItemState($arguments['item'])['name'] ?? null)
                                                                            ))
                                                                            ->reorderableWithButtons()
                                                                            ->extraAttributes(['class' => 'company-departments-repeater border-l-4 border-primary-500 pl-4']),
                                                                    ]),
                                                            ]),
                                                    ]),
                                            ]),
                                    ]),

                                Tabs\Tab::make(__('Supervisors'))
                                    ->icon('heroicon-o-user-group')
                                    ->schema([
                                        View::make('ppuds::livewire.pages.company.details.supervisors')
                                            ->columnSpanFull()
                                            ->viewData(fn () => [
                                                'company' => $this->company,
                                                'supervisors' => $this->companySupervisorRows(),
                                                'unassignedDepartments' => $this->unassignedDepartmentRows(),
                                            ]),
                                    ]),

                                // التعديل هنا: تحويل "تدريبات الطلاب" إلى مفتاح ترجمة
                                Tabs\Tab::make(__('Student Trainings'))
                                    ->icon('heroicon-o-academic-cap')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(\Modules\PPUDS\Livewire\Pages\Company\Details\StudentCompany\Index::class,
                                                    [
                                                        'companyId' => $this->company->id,
                                                    ]
                                                )
                                                    ->columnSpanFull()
                                                    ->lazy(),
                                            ]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * تدريبات بلا مقعد مشرف: طلاب فرعهم وقسمهم لا يقابلهما صف في branch_department،
     * فلا يراهم أي مشرف شركة. تحدث حين يُحذف المشرف، إذ يُسقط الحذف صف الربط
     * بالكامل (CASCADE) — و user_id لا يقبل NULL أصلاً، فالصف يختفي ولا يفرغ.
     * جدول المشرفين مجمَّع حسب المشرف، فهذه الحالة تختفي منه ولا يبقى زر لإصلاحها.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function unassignedDepartmentRows(): Collection
    {
        $pivotTable = config('ppuds.table_prefix').'branch_department';
        $studentCompanyTable = (new StudentCompany)->getTable();

        return StudentCompany::query()
            ->where('company_id', $this->company->getKey())
            ->whereNotNull('branch_id')
            ->whereNotNull('department_id')
            // whereNotNull يغطي الحالتين معاً: صف ربط مفقود (حذف قديم قبل تحويل
            // القيد إلى SET NULL)، وصف باقٍ بمشرف فارغ (الحذف بعد التحويل).
            ->whereNotExists(fn ($subQuery) => $subQuery
                ->select(DB::raw(1))
                ->from($pivotTable)
                ->whereColumn("{$pivotTable}.branch_id", "{$studentCompanyTable}.branch_id")
                ->whereColumn("{$pivotTable}.company_department_id", "{$studentCompanyTable}.department_id")
                ->whereNotNull("{$pivotTable}.user_id"))
            ->with(['branch', 'department'])
            ->get()
            ->groupBy(fn (StudentCompany $placement): string => $placement->branch_id.'-'.$placement->department_id)
            ->map(function (Collection $placements): array {
                $first = $placements->first();

                return [
                    'branch_id' => $first->branch_id,
                    'branch' => $first->branch?->name,
                    'department_id' => $first->department_id,
                    'department' => $first->department?->name,
                    'students_count' => $placements->count(),
                ];
            })
            ->values();
    }

    protected function companySupervisorRows(): Collection
    {
        $this->company->loadMissing(['branches.departments']);

        $assignments = $this->company->branches
            ->flatMap(function (Branch $branch) {
                return $branch->departments->map(function (CompanyDepartment $department) use ($branch) {
                    $userId = $department->pivot->user_id ?? null;

                    if (! $userId) {
                        return null;
                    }

                    return [
                        'user_id' => (int) $userId,
                        'branch_id' => $branch->id,
                        'branch' => $branch->name,
                        'department_id' => $department->id,
                        'department' => $department->name,
                    ];
                });
            })
            ->filter();

        if ($assignments->isEmpty()) {
            return collect();
        }

        $supervisorIds = $assignments->pluck('user_id')->unique()->values();

        $users = User::whereIn('id', $supervisorIds)
            ->with('media')
            ->get()
            ->keyBy('id');

        return $assignments
            ->groupBy('user_id')
            ->map(function (Collection $userAssignments, int $userId) use ($users) {
                $user = $users->get($userId);

                if (! $user) {
                    return null;
                }

                return [
                    'user' => $user,
                    'branches' => $userAssignments->pluck('branch')->unique()->values(),
                    'departments' => $userAssignments
                        ->unique(fn (array $assignment) => "{$assignment['branch']}-{$assignment['department']}")
                        ->values(),
                ];
            })
            ->filter()
            ->values();
    }

    public function editDepartmentSupervisorAction(): Action
    {
        return Action::make('editDepartmentSupervisor')
            ->label(__('Edit Supervisor'))
            ->modalHeading(__('Edit Supervisor'))
            ->icon('heroicon-o-pencil-square')
            ->form([
                Select::make('user_id')
                    ->label(__('Supervisor'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(fn () => User::role('Company Supervisor')->pluck('name', 'id')),
            ])
            ->fillForm(fn (array $arguments): array => [
                'user_id' => $arguments['userId'] ?? null,
            ])
            ->action(function (array $data, array $arguments): void {
                abort_unless(auth()->user()?->can('Company Update'), 403);

                $branch = $this->company->branches()->whereKey($arguments['branchId'] ?? null)->first();

                abort_unless($branch, 404);

                $departmentId = $arguments['departmentId'] ?? null;

                abort_unless($departmentId, 404);

                // حذف مستخدم يُسقط صف branch_department بالكامل (CASCADE) لا يفرّغه،
                // و updateExistingPivot لا يُنشئ صفاً — فكان الإسناد يفشل بصمت ويعرض
                // رسالة نجاح. لذلك نفحص الارتباط أولاً ونُنشئه إن كان مفقوداً.
                $isAttached = $branch->departments()->whereKey($departmentId)->exists();

                if ($isAttached) {
                    $branch->departments()->updateExistingPivot($departmentId, [
                        'user_id' => $data['user_id'],
                    ]);
                } else {
                    $branch->departments()->attach($departmentId, [
                        'user_id' => $data['user_id'],
                    ]);
                }

                Toaster::success(__('Supervisor updated successfully'));
            })
            ->visible(fn () => auth()->user()?->can('Company Update'));
    }

    public function downloadAttachment(int $mediaId)
    {
        $media = $this->companyAttachment($mediaId);

        abort_unless($media, 404);

        return response()->download($media->getPath(), $media->file_name, [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function deleteAttachment(int $mediaId): void
    {
        $media = $this->companyAttachment($mediaId);

        if (! $media) {
            Toaster::error(__('Attachment not found'));

            return;
        }

        $media->delete();
        $this->company->unsetRelation('media');
        $this->company->load('media');

        Toaster::success(__('Attachment deleted successfully'));
    }

    protected function companyAttachment(int $mediaId): ?SpatieMedia
    {
        return SpatieMedia::query()
            ->whereKey($mediaId)
            ->where('model_type', $this->company->getMorphClass())
            ->where('model_id', $this->company->getKey())
            ->where('collection_name', 'attachments')
            ->first();
    }

    public function save()
    {
        abort_unless(auth()->user()?->can('Company Update'), 403);

        // 1. التحقق من البيانات
        $this->validate();

        // لا يُحفظ شيء إذا أُزيل فرع أو قسم عليه تدريبات طلاب. يُفحص قبل getState()
        // لأنها تُسقط الشعار والغلاف من الحالة، فيحذفهما الحفظ التالي بعد الرفض.
        if (! $this->linkedStructureIsKept()) {
            return null;
        }

        $this->data = $this->form->getState();
        $this->mergePendingCreatedSupervisorAssignmentsIntoFormData();

        // 2. تحديث بيانات الشركة الأساسية (استبعاد الفروع والشعار مؤقتاً)
        $attachmentUploads = $this->data['attachment_uploads'] ?? [];
        $companyData = Arr::except($this->data, ['branches', 'logo', 'cover_photo', 'attachments', 'attachment_files', 'attachment_uploads']);
        $companyData['description'] = blank($companyData['description'] ?? null) ? null : $companyData['description'];
        $this->company->update($companyData);

        // 3. حفظ الصور (الشعار والغلاف)
        $this->form->model($this->company)->saveRelationships();

        // 3.1 حفظ مرفقات الشركة بنفس أسلوب الإضافة اليدوية
        $this->saveAttachments($attachmentUploads);

        $previousSupervisorIds = $this->persistedCompanySupervisorIds();

        // 4. حفظ الفروع والأقسام وساعات العمل
        $this->saveBranchesAndDepartments();

        // 4.1 إرسال المشرفين الجدد إلى نظام الجامعة عبر Company/Add
        $this->syncAddedSupervisorsToUniversity($previousSupervisorIds);

        // 5. رسالة نجاح
        Toaster::success(__('Saved successfully'));

        // إعادة التوجيه للصفحة الحالية لتحديث البيانات
        return redirect()->route('companies.details', $this->company);
    }

    protected function saveAttachments(array $attachmentUploads): void
    {
        foreach ($attachmentUploads as $attachmentUpload) {
            $files = Arr::wrap($attachmentUpload['file'] ?? []);
            $name = $attachmentUpload['name'] ?? null;

            foreach (array_filter($files) as $attachmentFile) {
                $this->company->addAttachment($attachmentFile, $name);
            }
        }
    }

    protected function saveBranchesAndDepartments()
    {
        $formBranches = $this->data['branches'] ?? [];
        $processedBranchIds = [];

        foreach ($formBranches as $branchData) {

            $branchId = $branchData['id'] ?? null;

            // استخراج البيانات الفرعية
            $departmentsData = $branchData['departments'] ?? [];
            $workingHoursData = $branchData['working_hours'] ?? [];

            // تنظيف بيانات الفرع
            $branchAttributes = Arr::except($branchData, ['departments', 'working_hours', 'id', 'location']);

            $branch = null;

            // الفرع المنسوخ بزر النسخ يحمل id الأصل، فيُنشأ فرعاً جديداً بدل أن يكتب فوق الأصل.
            if ($branchId && in_array((int) $branchId, $processedBranchIds, true)) {
                $branchId = null;
            }

            // --- أ. التعامل مع الفرع (تحديث أو إنشاء) ---
            if ($branchId) {
                // تحديث فرع موجود — من فروع هذه الشركة فقط
                $branch = $this->company->branches()->whereKey((int) $branchId)->first();
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

                // --- ب. حفظ الأقسام ---
                $this->syncDepartmentsForBranch($branch, $departmentsData);

                // --- ج. حفظ ساعات العمل ---
                foreach ($workingHoursData as $wh) {
                    $branch->workingHours()->updateOrCreate(
                        ['day' => $wh['day']],
                        [
                            'is_closed' => $wh['is_closed'],
                            'start_time' => $wh['is_closed'] ? null : $wh['start_time'],
                            'end_time' => $wh['is_closed'] ? null : $wh['end_time'],
                        ]
                    );
                }
            }
        }

        // --- د. حذف الفروع التي تمت إزالتها من النموذج ---
        // نحصل على معرفات الفروع الحالية للشركة
        $currentCompanyBranchIds = $this->company->branches()->pluck('branch_branches.id')->toArray();

        // الفروع التي يجب فصلها هي الموجودة في الداتابيز ولكن غير موجودة في الـ processedBranchIds
        $branchesToDetach = $this->branchesSafeToDetach(
            array_diff($currentCompanyBranchIds, $processedBranchIds)
        );

        if (! empty($branchesToDetach)) {
            $this->company->branches()->detach($branchesToDetach);
            // Branch::destroy($branchesToDetach); // اختياري: إذا أردت الحذف النهائي
        }
    }

    /**
     * الفرع الذي عليه تدريبات طلاب لا يُفصل عن الشركة: التدريب يخزّن
     * company_id و branch_id معاً، وفصل الفرع يترك التدريب معلّقاً على فرع
     * لم تعد الشركة تملكه، فتفقد قائمة الفروع في شاشة التدريب اسمه.
     *
     * @param  array<int, int>  $branchIds
     * @return array<int, int>
     */
    protected function branchesSafeToDetach(array $branchIds): array
    {
        if (empty($branchIds)) {
            return [];
        }

        $usedBranchIds = StudentCompany::query()
            ->where('company_id', $this->company->id)
            ->whereIn('branch_id', $branchIds)
            ->distinct()
            ->pluck('branch_id')
            ->all();

        if (empty($usedBranchIds)) {
            return $branchIds;
        }

        $names = Branch::whereKey($usedBranchIds)->get()->pluck('name')->filter();

        Toaster::warning(__('These branches were kept because student placements are recorded on them: :branches', [
            'branches' => $names->isNotEmpty() ? $names->implode(', ') : implode(', ', $usedBranchIds),
        ]));

        return array_values(array_diff($branchIds, $usedBranchIds));
    }

    /**
     * الفروع والأقسام الحالية للشركة التي عليها تدريبات طلاب:
     * branch id => ids الأقسام المربوطة بصف في branch_department.
     * حذف صف القسم يُخفي الطلاب عن مشرف الشركة، وفصل الفرع يترك التدريب معلّقاً.
     *
     * @return array<int, array<int, int>>
     */
    protected function linkedDepartmentIdsByBranch(): array
    {
        if ($this->linkedDepartmentIdsCache !== null) {
            return $this->linkedDepartmentIdsCache;
        }

        $companyBranches = $this->company->branches()->with('departments')->get()->keyBy('id');

        $placements = StudentCompany::query()
            ->where('company_id', $this->company->id)
            ->whereIn('branch_id', $companyBranches->keys())
            ->select(['branch_id', 'department_id'])
            ->distinct()
            ->get();

        $linked = [];

        foreach ($placements as $placement) {
            $branchId = (int) $placement->branch_id;
            $linked[$branchId] ??= [];

            $seatedDepartmentIds = $companyBranches->get($branchId)->departments->pluck('id')->map(fn ($id): int => (int) $id)->all();

            if ($placement->department_id && in_array((int) $placement->department_id, $seatedDepartmentIds, true)) {
                $linked[$branchId][] = (int) $placement->department_id;
            }
        }

        return $this->linkedDepartmentIdsCache = array_map(fn (array $ids): array => array_values(array_unique($ids)), $linked);
    }

    protected function isBranchLinked(mixed $branchId): bool
    {
        return filled($branchId) && array_key_exists((int) $branchId, $this->linkedDepartmentIdsByBranch());
    }

    protected function isDepartmentLinked(mixed $branchId, mixed $departmentName): bool
    {
        if (blank($branchId) || blank($departmentName)) {
            return false;
        }

        $linkedIds = $this->linkedDepartmentIdsByBranch()[(int) $branchId] ?? [];

        if ($linkedIds === []) {
            return false;
        }

        $departmentId = CompanyDepartment::whereTranslation('name', trim((string) $departmentName))->value('id');

        return $departmentId && in_array((int) $departmentId, $linkedIds, true);
    }

    /**
     * يمنع الحفظ إذا حُذف (أو أُعيدت تسميته) فرع أو قسم عليه تدريبات طلاب.
     */
    protected function linkedStructureIsKept(): bool
    {
        $linked = $this->linkedDepartmentIdsByBranch();

        if ($linked === []) {
            return true;
        }

        $formBranches = collect($this->data['branches'] ?? [])
            ->filter(fn (mixed $branch): bool => is_array($branch) && filled($branch['id'] ?? null))
            ->keyBy(fn (array $branch): int => (int) $branch['id']);

        $removedBranchIds = [];
        $removedDepartments = [];

        foreach ($linked as $branchId => $departmentIds) {
            $branchData = $formBranches->get($branchId);

            if (! $branchData) {
                $removedBranchIds[] = $branchId;

                continue;
            }

            $keptDepartmentIds = collect($branchData['departments'] ?? [])
                ->pluck('name')
                ->filter()
                ->map(fn (mixed $name) => CompanyDepartment::whereTranslation('name', trim((string) $name))->value('id'))
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            foreach (array_diff($departmentIds, $keptDepartmentIds) as $departmentId) {
                $removedDepartments[] = [$branchId, $departmentId];
            }
        }

        if ($removedBranchIds === [] && $removedDepartments === []) {
            return true;
        }

        // تُضاف كأخطاء على الفروع لتظهر في ملخص الأخطاء أعلى الصفحة بدل رسالة منبثقة تختفي.
        if ($removedBranchIds !== []) {
            $this->addError('data.branches', __('These branches cannot be deleted because student placements are recorded on them: :branches', [
                'branches' => Branch::whereKey($removedBranchIds)->get()->pluck('name')->filter()->implode(', ') ?: implode(', ', $removedBranchIds),
            ]));
        }

        if ($removedDepartments !== []) {
            $branchNames = Branch::whereKey(array_column($removedDepartments, 0))->get()->pluck('name', 'id');
            $departmentNames = CompanyDepartment::whereKey(array_column($removedDepartments, 1))->get()->pluck('name', 'id');

            $this->addError('data.branches', __('These departments cannot be deleted because student placements are recorded on them: :departments', [
                'departments' => collect($removedDepartments)
                    ->map(fn (array $pair): string => ($branchNames[$pair[0]] ?? $pair[0]).' / '.($departmentNames[$pair[1]] ?? $pair[1]))
                    ->implode(', '),
            ]));
        }

        $this->dispatch('form-validation-error', livewireId: $this->getId());

        return false;
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

    private function attachCreatedSupervisorToCompanyDepartment(Get $get, int $supervisorId, string $plainPassword): void
    {
        $branchId = $get('../../id');
        $departmentName = $get('name');

        if (blank($branchId) || blank($departmentName)) {
            return;
        }

        $branch = $this->company
            ->branches()
            ->whereKey((int) $branchId)
            ->first();

        if (! $branch) {
            return;
        }

        $department = $this->resolveCompanyDepartment(trim((string) $departmentName));
        $now = now();

        DB::table(config('ppuds.table_prefix').'branch_department')->updateOrInsert(
            [
                'branch_id' => $branch->id,
                'company_department_id' => $department->id,
            ],
            [
                'user_id' => $supervisorId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $this->pendingCreatedSupervisorAssignments[] = [
            'branch_id' => $branch->id,
            'department_name' => trim((string) $departmentName),
            'user_id' => $supervisorId,
        ];

        $this->syncSingleSupervisorToUniversity($supervisorId, $plainPassword);
    }

    /**
     * مثل صفحة التعديل: يملأ المشرف المُنشأ من نافذة "إضافة" في قسمه فقط إن
     * كان القسم بلا مشرف، ولا يضيف أقساماً جديدة — إضافتها كانت تخترع قسماً
     * ثانياً بنفس المشرف إذا تغيّر اسم القسم بعد إنشاء المشرف.
     */
    private function mergePendingCreatedSupervisorAssignmentsIntoFormData(): void
    {
        if ($this->pendingCreatedSupervisorAssignments === []) {
            return;
        }

        foreach ($this->pendingCreatedSupervisorAssignments as $assignment) {
            foreach ($this->data['branches'] ?? [] as &$branch) {
                if ((int) ($branch['id'] ?? 0) !== (int) $assignment['branch_id']) {
                    continue;
                }

                foreach ($branch['departments'] ?? [] as &$department) {
                    if (trim((string) ($department['name'] ?? '')) !== $assignment['department_name']) {
                        continue;
                    }

                    // لا نستبدل مشرفاً اختاره المستخدم بعد ذلك.
                    if (blank($department['user_id'] ?? null)) {
                        $department['user_id'] = (int) $assignment['user_id'];
                    }

                    continue 3;
                }

                unset($department);
            }

            unset($branch);
        }

        // استُهلكت — حفظ لاحق يجب ألا يعيد تطبيقها على فرع آخر.
        $this->pendingCreatedSupervisorAssignments = [];
    }

    private function syncAddedSupervisorsToUniversity(array $previousSupervisorIds): void
    {
        $addedSupervisorIds = array_values(array_diff(
            $this->selectedCompanySupervisorIds(),
            $previousSupervisorIds,
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

    private function selectedCompanySupervisorIds(): array
    {
        return $this->supervisorIdsFromBranches($this->data['branches'] ?? []);
    }

    private function persistedCompanySupervisorIds(): array
    {
        $company = $this->company->fresh(['branches.supervisors']);

        if (! $company) {
            return [];
        }

        return $company->companySupervisors()
            ->pluck('id')
            ->map(fn (mixed $supervisorId): int => (int) $supervisorId)
            ->values()
            ->all();
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

    private function syncSingleSupervisorToUniversity(int $supervisorId, ?string $plainPassword = null): void
    {
        $company = $this->company->fresh(['branches.supervisors', 'translations']);

        if (! $company) {
            return;
        }

        $result = app(PpuApiService::class)->addCompanyToUniversity(
            $company,
            $plainPassword,
            $supervisorId,
            sendEvenIfCompanyExists: true,
        );

        if (($result['operation'] ?? null) === 'already_exists') {
            Toaster::success(__('Company supervisor already exists in university system'));

            return;
        }

        if (($result['success'] ?? null) === false) {
            Toaster::error(__('Unable to send company supervisor to university system'));

            return;
        }

        if ($result !== null) {
            Toaster::success(__('Company supervisor sent to university successfully'));
        }
    }

    public function render()
    {
        return view('ppuds::livewire.pages.company.details.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Companies List'), 'url' => route('companies.index')],
                ['title' => __('Company Details'), 'url' => route('companies.details', $this->company)],
            ],
        ]);
    }
}

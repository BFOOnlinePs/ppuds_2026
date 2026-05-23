<?php

namespace Modules\PPUDS\Livewire\Pages\Company\Details;

use App\View\Components\AppLayout;
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
use Illuminate\Support\HtmlString;
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
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Details extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public ?array $data = [];

    public Company $company;

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
                'country_id' => $branch->country_id,
                'city_id' => $branch->city_id,
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'location' => [
                    'lat' => (float) ($branch->latitude ?: 31.5326),
                    'lng' => (float) ($branch->longitude ?: 35.0998),
                ],
                'working_hours' => $workingHoursData,
                'departments' => $branch->departments->map(function ($dept) {
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
                                            ->grid(1)
                                            ->extraAttributes(['class' => 'gap-6 company-structure-repeater'])
                                            ->schema([
                                                TextInput::make('id')->hidden(),

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
                                                                                    ->options(Country::get()->pluck('name', 'id'))
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

                                                                                        return City::whereHas('governorate', function (Builder $query) use ($countryId) {
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
                        'branch' => $branch->name,
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

        // 2. تحديث بيانات الشركة الأساسية (استبعاد الفروع والشعار مؤقتاً)
        $attachmentUploads = $this->data['attachment_uploads'] ?? [];
        $companyData = Arr::except($this->data, ['branches', 'logo', 'cover_photo', 'attachments', 'attachment_files', 'attachment_uploads']);
        $companyData['description'] = blank($companyData['description'] ?? null) ? null : $companyData['description'];
        $this->company->update($companyData);

        // 3. حفظ الصور (الشعار والغلاف)
        $this->form->model($this->company)->saveRelationships();

        // 3.1 حفظ مرفقات الشركة بنفس أسلوب الإضافة اليدوية
        $this->saveAttachments($attachmentUploads);

        // 4. حفظ الفروع والأقسام وساعات العمل
        $this->saveBranchesAndDepartments();

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

            // --- أ. التعامل مع الفرع (تحديث أو إنشاء) ---
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
        $branchesToDetach = array_diff($currentCompanyBranchIds, $processedBranchIds);

        if (! empty($branchesToDetach)) {
            $this->company->branches()->detach($branchesToDetach);
            // Branch::destroy($branchesToDetach); // اختياري: إذا أردت الحذف النهائي
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
                    'name' => $deptName,
                    'created_by' => auth()->id(),
                ]);
            }

            $syncData[$department->id] = ['user_id' => $userId];
        }

        $branch->departments()->sync($syncData);
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

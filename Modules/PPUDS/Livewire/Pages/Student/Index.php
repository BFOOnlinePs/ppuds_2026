<?php

namespace Modules\PPUDS\Livewire\Pages\Student;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Maatwebsite\Excel\Excel as WriterType;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Enums\CvStatus;
use Modules\PPUDS\Enums\StudentGender;
use Modules\PPUDS\Exports\StudentsExport;
use Modules\PPUDS\Services\PpuApiService;
use Modules\PPUDS\Settings\GeneralSettings;
use Throwable;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table)
    {
        return $table
            ->query(fn () => StudentProfile::query()
                ->with('user')
                ->when(request()->boolean('not_matched'), fn ($query) => $query
                    ->whereDoesntHave(
                        'user.studentCompanies',
                        fn ($studentCompanyQuery) => $studentCompanyQuery->whereNotNull('company_id')
                    )))
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('Arabic Name'))
                    ->searchable()
                    ->url(fn (StudentProfile $record) => route('students.details', $record->user_id))
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('user.name_en')
                    ->label(__('English Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->sortable(),

            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                Action::make('export_students')
                    ->label(__('Export Students'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => app(ExcelServiceInterface::class)->download(
                        new StudentsExport($this->getTableQueryForExport()),
                        $this->exportFilename(),
                        WriterType::XLSX
                    ))
                    ->visible(fn () => auth()->user()->can('Student View List')),

                Action::make('sync_student')
                    ->label(__('Sync Student'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (PpuApiService $service) {
                        $status = $service->syncStudents(app(GeneralSettings::class)->year, app(GeneralSettings::class)->semester_type->value);
                        if ($status) {
                            Toaster::success(__('Sync Major').' '.($status ? __('Success') : __('Failed')));
                        }
                    }),

                CreateAction::make('create')
                    ->label(__('Add Student'))
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading(__('Add Student'))
                    ->modalSubmitActionLabel(__('Save'))
                    ->modalWidth('7xl')
                    ->form($this->getStudentFormSchema())
                    ->using(fn (array $data): StudentProfile => $this->createStudent($data))
                    ->after(fn () => Toaster::success(__('Student created successfully')))
                    ->visible(fn () => auth()->user()->can('Student Create')),
            ])
            ->bulkActions([]);
    }

    protected function getStudentFormSchema(): array
    {
        return [
            Grid::make(3)
                ->schema([
                    Grid::make(1)
                        ->columnSpan(2)
                        ->schema([
                            Section::make(__('Account Information'))
                                ->description(__('Manage user login details'))
                                ->icon('heroicon-o-user')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('name')
                                        ->label(__('Name (Arabic)'))
                                        ->required()
                                        ->maxLength(255),

                                    TextInput::make('name_en')
                                        ->label(__('Name (English)'))
                                        ->required()
                                        ->maxLength(255),

                                    TextInput::make('email')
                                        ->label(__('Email Address'))
                                        ->email()
                                        ->required()
                                        ->unique('users', 'email'),

                                    TextInput::make('phone')
                                        ->label(__('Phone'))
                                        ->numeric()
                                        ->unique('users', 'phone')
                                        ->required(),

                                    TextInput::make('password')
                                        ->label(__('Password'))
                                        ->password()
                                        ->revealable()
                                        ->required()
                                        ->confirmed(),

                                    TextInput::make('password_confirmation')
                                        ->label(__('Confirm Password'))
                                        ->password()
                                        ->revealable()
                                        ->required(),
                                ]),

                            Section::make(__('Academic Information'))
                                ->description(__('Student university details'))
                                ->icon('heroicon-o-academic-cap')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('student_number')
                                        ->label(__('Student Number'))
                                        ->required()
                                        ->unique(config('ppuds.table_prefix').'student_profiles', 'student_number')
                                        ->numeric(),

                                    Select::make('major_id')
                                        ->label(__('Major'))
                                        ->options(fn () => Major::get()->pluck('name', 'id'))
                                        ->createOptionForm([
                                            TextInput::make('reference_code')
                                                ->label(__('Reference code'))
                                                ->required(),

                                            TextInput::make('name')
                                                ->label(__('Name'))
                                                ->required(),

                                            Textarea::make('description')
                                                ->label(__('Description')),
                                        ])
                                        ->createOptionUsing(function (array $data) {
                                            $data['created_by'] = auth()->id();

                                            return Major::create($data)->getKey();
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->required(),

                                    TextInput::make('enrollment_year')
                                        ->label(__('Enrollment Year'))
                                        ->numeric()
                                        ->minValue(2000)
                                        ->maxValue(date('Y') + 1)
                                        ->default(date('Y')),

                                    Select::make('semester_level')
                                        ->label(__('Semester Level'))
                                        ->options(array_combine(range(1, 10), range(1, 10)))
                                        ->nullable(),
                                ]),
                        ]),

                    Grid::make(1)
                        ->columnSpan(1)
                        ->schema([
                            Section::make(__('Personal Details'))
                                ->icon('heroicon-o-identification')
                                ->schema([
                                    DatePicker::make('dob')
                                        ->label(__('Date of Birth'))
                                        ->maxDate(now()),

                                    Select::make('gender')
                                        ->label(__('Gender'))
                                        ->options(StudentGender::options()),

                                    TextInput::make('tawjihi_gpa')
                                        ->label(__('Tawjihi GPA'))
                                        ->numeric()
                                        ->maxValue(100)
                                        ->suffix('%'),
                                ]),

                            Section::make(__('Social Links'))
                                ->icon('heroicon-o-link')
                                ->schema([
                                    TextInput::make('linkedin_url')
                                        ->label(__('LinkedIn'))
                                        ->url()
                                        ->maxLength(255),

                                    TextInput::make('behance_url')
                                        ->label(__('Behance'))
                                        ->url()
                                        ->maxLength(255),

                                    TextInput::make('github_url')
                                        ->label(__('GitHub'))
                                        ->url()
                                        ->maxLength(255),
                                ]),
                        ]),
                ]),
        ];
    }

    protected function createStudent(array $data): StudentProfile
    {
        abort_unless(auth()->user()->can('Student Create'), 403);

        return DB::transaction(function () use ($data): StudentProfile {
            $user = User::create([
                'name' => $data['name'],
                'name_en' => $data['name_en'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
            ]);

            $user->generateAvatar();
            $user->assignRole(UserRole::STUDENT->value);

            $profileData = collect($data)
                ->except(['name', 'name_en', 'email', 'phone', 'password', 'password_confirmation', 'roles'])
                ->toArray();

            return StudentProfile::create(array_merge($profileData, [
                'user_id' => $user->id,
                'cv_status' => CvStatus::PENDING->value,
            ]));
        });
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('user_email')
                ->form([
                    Forms\Components\TextInput::make('email')
                        ->label(__('Email')),
                ])
                ->query(function ($query, array $data) {
                    if (empty($data['email'])) {
                        return $query;
                    }

                    return $query->whereHas('user', function ($q) use ($data) {
                        $q->where('email', 'like', '%'.$data['email'].'%');
                    });
                }),
            Filter::make('user_name')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Name')),
                ])
                ->query(function ($query, array $data) {
                    return $query->whereHas('user', function ($q) use ($data) {
                        $q->where('name', 'like', '%'.$data['name'].'%');
                    });
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make('view')
                ->label('')
                ->modalHeading(__('Student Details'))
                ->form(function ($record) {
                    return [
                        Grid::make(3)
                            ->schema([
                                Grid::make(1)
                                    ->columnSpan(2)
                                    ->schema([
                                        Section::make(__('Account Information'))
                                            ->icon('heroicon-o-user')
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('name_ar')
                                                    ->label(__('Name (Arabic)'))
                                                    ->default($record->user->name ?? '-')
                                                    ->disabled(),

                                                TextInput::make('name_en')
                                                    ->label(__('Name (English)'))
                                                    ->default($record->user->name_en ?? '-')
                                                    ->disabled(),

                                                TextInput::make('email')
                                                    ->label(__('Email Address'))
                                                    ->default($record->user->email ?? '-')
                                                    ->columnSpanFull()
                                                    ->disabled(),
                                            ]),

                                        // القسم 2: المعلومات الأكاديمية (من جدول student_profiles)
                                        Section::make(__('Academic Information'))
                                            ->icon('heroicon-o-academic-cap')
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('student_number')
                                                    ->label(__('Student Number'))
                                                    ->default($record->student_number)
                                                    ->disabled(),

                                                // عرض اسم التخصص بدلاً من الرقم
                                                TextInput::make('major_name')
                                                    ->label(__('Major'))
                                                    ->default($record->major->name ?? '-')
                                                    ->disabled(),

                                                TextInput::make('enrollment_year')
                                                    ->label(__('Enrollment Year'))
                                                    ->default($record->enrollment_year)
                                                    ->disabled(),

                                                TextInput::make('semester_level')
                                                    ->label(__('Semester Level'))
                                                    ->default($record->semester_level)
                                                    ->disabled(),
                                            ]),
                                    ]),

                                // العمود الثالث: البيانات الشخصية
                                Grid::make(1)
                                    ->columnSpan(1)
                                    ->schema([
                                        Section::make(__('Personal Details'))
                                            ->icon('heroicon-o-identification')
                                            ->schema([
                                                Forms\Components\DatePicker::make('dob')
                                                    ->label(__('Date of Birth'))
                                                    ->default($record->dob)
                                                    ->disabled(),

                                                Forms\Components\Select::make('gender')
                                                    ->label(__('Gender'))
                                                    ->options(collect(StudentGender::cases())->pluck('name', 'value'))
                                                    ->default($record->gender)
                                                    ->disabled(),

                                                TextInput::make('tawjihi_gpa')
                                                    ->label(__('Tawjihi GPA'))
                                                    ->default($record->tawjihi_gpa)
                                                    ->suffix('%')
                                                    ->disabled(),

                                                // عرض الأدوار
                                                TextInput::make('roles')
                                                    ->label(__('Roles'))
                                                    ->default($record->user->roles->pluck('name')->implode(', '))
                                                    ->disabled(),
                                            ]),

                                        Section::make(__('Social Links'))
                                            ->icon('heroicon-o-link')
                                            ->schema([
                                                TextInput::make('linkedin_url')
                                                    ->label(__('LinkedIn'))
                                                    ->default($record->linkedin_url)
                                                    ->disabled(),

                                                TextInput::make('behance_url')
                                                    ->label(__('Behance'))
                                                    ->default($record->behance_url)
                                                    ->disabled(),

                                                TextInput::make('github_url')
                                                    ->label(__('GitHub'))
                                                    ->default($record->github_url)
                                                    ->disabled(),
                                            ]),
                                    ]),
                            ]),
                    ];
                })
                ->modalSubmitAction(false)
                ->visible(fn () => auth()->user()->can('Student View')),

            Action::make('details')
                ->label('')
                ->icon('heroicon-o-user')
                ->url(fn ($record) => route('students.details', $record->user_id))
                ->visible(fn () => auth()->user()->can('Student Details List')),

            //            EditAction::make('edit')
            //                ->url(fn(StudentProfile $record) => route('students.edit', $record->user_id))
            //                ->visible(fn() => auth()->user()->can('Student Update')),
            DeleteAction::make('delete')
                ->action(fn (StudentProfile $record) => $this->deleteStudent($record))
                ->visible(fn () => auth()->user()->can('Student Delete')),
        ];
    }

    protected function deleteStudent(StudentProfile $studentProfile): void
    {
        abort_unless(auth()->user()->can('Student Delete'), 403);

        if ((int) $studentProfile->user_id === (int) auth()->id()) {
            Toaster::error(__('You cannot delete your own account.'));

            return;
        }

        try {
            DB::transaction(function () use ($studentProfile): void {
                $user = $studentProfile->user;

                if (! $user) {
                    $studentProfile->delete();

                    return;
                }

                $user->syncRoles([]);
                $user->tokens()->delete();
                $user->delete();
            });

            Toaster::success(__('Student deleted successfully'));
        } catch (Throwable $exception) {
            report($exception);

            Toaster::error(__('Student could not be deleted because it is linked to other records.'));
        }
    }

    protected function exportFilename(): string
    {
        return 'students-'.now()->format('Y-m-d-His').'.xlsx';
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Students List'), 'url' => route('students.index')],
            ],
        ]);
    }
}

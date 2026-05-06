<?php

namespace Modules\PPUDS\Livewire\Pages\Company\Details\StudentCompany;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Payment;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?int $companyId = null;

    public function mount(?int $companyId = null)
    {
        $this->companyId = $companyId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => StudentCompany::query()->where('company_id', $this->companyId)->with(['registration.student', 'registration.course', 'company', 'branch']))
            ->columns([
                //                TextColumn::make('registration.student.name')
                //                    ->label(__('Student'))
                //                    ->searchable()
                //                    ->sortable()
                //                    ->weight('bold')
                //                    ->color('primary')
                //                    ->url(fn (StudentCompany $record) => route('student-companies.edit', $record))
                //                    ->description(fn (StudentCompany $record) => $record->registration?->student?->email),

                TextColumn::make('student.name')
                    ->label(__('Student'))
                    //                    ->url(fn (StudentCompany $record) => route('student.edit', $record->company_id))
                    ->searchable()
                    ->placeholder('—')
                    ->color('primary'),

                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('registration.course.name')
                    ->label(__('Course'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('registration.semester')
                    ->label(__('Semester'))
                    ->toggleable(),

                TextColumn::make('registration.semester')
                    ->label(__('Semester')),

                TextColumn::make('registration.year')
                    ->label(__('Year')),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('Y-m-d')
                    ->icon('solar-clock-circle-bold-duotone')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            //            ->actions($this->getTableActions())
            ->headerActions([
                \Modules\Core\Filament\Forms\Components\CreateAction::make('create')
                    ->label(__('Add Student Company'))
                    // ->url(route('student-companies.add'))
                    ->form([
                        Grid::make(['default' => 1, 'lg' => 3])
                            ->schema([
                                Group::make()
                                    ->columnSpan(['lg' => 2])
                                    ->schema([

                                        Section::make(__('Student & Registration Info'))
                                            ->icon('solar-user-id-bold-duotone')
                                            ->schema([
                                                Select::make('registration_id')
                                                    ->label(__('Select Student Registration'))
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    ->prefixIcon('solar-document-text-linear')
                                                    ->options(function () {
                                                        return Registration::with(['student', 'course'])
                                                            ->where('semester', app(GeneralSettings::class)->semester_type->value)
                                                            ->where('year', app(GeneralSettings::class)->year)
                                                            ->get()
                                                            ->mapWithKeys(function ($reg) {
                                                                $semesterLabel = $reg->semester?->getLabel() ?? $reg->semester?->value;

                                                                return [$reg->id => "{$reg->student->studentProfile->student_number} - {$reg->student->name} - {$reg->course->name} ({$semesterLabel}/{$reg->year})"];
                                                            });
                                                    }),
                                            ]),

                                        Section::make(__('Placement Details'))
                                            ->icon('solar-buildings-2-bold-duotone')
                                            ->schema([
                                                Grid::make(2)->schema([

                                                    Select::make('company_id')
                                                        ->label(__('Company'))
                                                        ->options(Company::where('id', $this->companyId)->get()->pluck('name', 'id'))
                                                        ->searchable()
                                                        ->preload()
                                                        ->live()
                                                        ->default($this->companyId)
                                                        ->selectablePlaceholder(false)
                                                        ->afterStateUpdated(function (Set $set) {
                                                            $set('branch_id', null);
                                                            $set('department_id', null);
                                                        })
                                                        ->prefixIcon('solar-city-linear'),

                                                    Select::make('branch_id')
                                                        ->label(__('Branch'))
                                                        ->key('branchSelect')
                                                        ->searchable()
                                                        ->preload()
                                                        ->live()
                                                        ->afterStateUpdated(fn (Set $set) => $set('department_id', null))
                                                        ->prefixIcon('solar-map-point-linear')
                                                        ->placeholder(fn(Get $get) => $get('company_id') ? __('Select Branch') : __('Select Company First'))
                                                        ->disabled(fn(Get $get) => ! $get('company_id'))
                                                        ->options(
                                                            fn(Get $get) =>
                                                            Branch::whereHas('companies', function ($query) use ($get) {
                                                                $query->where('company_id', $get('company_id'));
                                                            })->get()->pluck('name', 'id')
                                                        ),

                                                    Select::make('department_id')
                                                        ->label(__('Department'))
                                                        ->key('deptSelect')
                                                        ->searchable()
                                                        ->preload()
                                                        ->prefixIcon('solar-users-group-two-rounded-linear')
                                                        ->placeholder(fn(Get $get) => $get('branch_id') ? __('Select Department') : __('Select Branch First'))
                                                        ->disabled(fn(Get $get) => ! $get('branch_id'))
                                                        ->options(function (Get $get) {
                                                            $branchId = $get('branch_id');

                                                            if (! $branchId) {
                                                                return [];
                                                            }

                                                            return CompanyDepartment::whereHas('branches', fn ($query) => $query->whereKey($branchId))
                                                                ->get()
                                                                ->pluck('name', 'id');
                                                        })
                                                        ->columnSpanFull(),
                                                ]),
                                            ]),
                                    ]),

                                Group::make()
                                    ->columnSpan(['lg' => 1])
                                    ->schema([
                                        Section::make(__('Status & Settings'))
                                            ->icon('solar-settings-bold-duotone')
                                            ->schema([
                                                Select::make('status')
                                                    ->label(__('Training Status'))
                                                    ->required()
                                                    ->options(TrainingStatus::class) // يدعم الـ Enum مباشرة كما فعلنا سابقاً
                                                    ->default(TrainingStatus::AVAILABLE)
                                                    ->native(false)
                                                    ->prefixIcon('solar-flag-bold-duotone'),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->action(function (array $data) {
                        $this->authorize('StudentCompany Create');

                        $data['created_by'] =   auth()->id();

                        $registration = Registration::findOrFail($data['registration_id']);
                        $data['student_id'] = $registration->student_id;

                        StudentCompany::create($data);

                        Toaster::success(__('Student company record created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('StudentCompany Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            // فلتر الحالة
            SelectFilter::make('status')
                ->label(__('Training Status'))
                ->options(TrainingStatus::class)
                ->native(false),

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(Company::get()->pluck('name', 'id'))
                ->searchable()
                ->preload(),

            SelectFilter::make('course')
                ->label(__('Course'))
                ->options(Course::get()->pluck('name', 'id'))
                ->query(function (Builder $query, array $data) {
                    return $query->when($data['value'], function ($q, $courseId) {
                        $q->whereHas('registration', fn($regQ) => $regQ->where('course_id', $courseId));
                    });
                })
                ->searchable(),

            Filter::make('year')
                ->form([
                    TextInput::make('year')
                        ->label(__('Academic Year'))
                        ->prefixIcon('solar-calendar-search-bold-duotone')
                        ->numeric()
                        ->default(app(GeneralSettings::class)->year)
                        ->placeholder(date('Y')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['year'],
                        fn(Builder $q, $year) => $q->whereHas('registration', fn($regQ) => $regQ->where('year', $year))
                    );
                }),

            Filter::make('semester_type')
                ->form([
                    Select::make('semester_type')
                        ->label(__('Semester Type'))
                        ->options(SemesterType::options())
                        ->default(app(GeneralSettings::class)->semester_type->value),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['semester_type'],
                        fn(Builder $q, $semester_type) => $q->whereHas('registration', fn($regQ) => $regQ->where('semester', $semester_type))
                    );
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('StudentCompany Info')),
            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->form(fn(StudentCompany $record) => [
                    Grid::make(2)->schema([
                        TextInput::make('student_name')
                            ->label(__('Student'))
                            ->default($record->registration?->student?->name)
                            ->disabled()
                            ->prefixIcon('solar-user-id-bold-duotone'),

                        TextInput::make('company_name')
                            ->label(__('Company'))
                            ->default($record->company?->name)
                            ->disabled()
                            ->prefixIcon('solar-city-bold-duotone'),

                        TextInput::make('status')
                            ->label(__('Status'))
                            ->default($record->status?->getLabel())
                            ->disabled()
                            ->prefixIcon('solar-flag-bold-duotone'),

                        TextInput::make('course_name')
                            ->label(__('Course'))
                            ->default($record->registration?->course?->name)
                            ->disabled()
                            ->prefixIcon('solar-book-bold-duotone'),
                    ]),
                ])
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('StudentCompany View')), // تأكد من اسم الصلاحية

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn(StudentCompany $record) => route('student-companies.edit', $record->id)) // تأكد من اسم الراوت
                ->visible(fn() => auth()->user()->can('StudentCompany Update')),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function ($record) {
                    $record->delete();
                    Toaster::success(__('Student company record deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('StudentCompany Delete')),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete Selected'))
                    ->icon('solar-trash-bin-trash-bold-duotone')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
                    ->after(fn() => Toaster::success(__('Selected records deleted successfully'))),
            ]),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.details.student-company.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Student Companies'), 'url' => route('student-companies.index')],
            ],
        ]);
    }
}

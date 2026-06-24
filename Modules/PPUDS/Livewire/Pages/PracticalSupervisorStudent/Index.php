<?php

namespace Modules\PPUDS\Livewire\Pages\PracticalSupervisorStudent;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->supervisedRegistrationsQuery()
                ->with([
                    'student.studentProfile.major',
                    'supervisor',
                    'course',
                    'studentCompany.company',
                    'studentCompany.branch',
                    'studentCompany.department',
                ]))
            ->columns([
                TextColumn::make('student.name')
                    ->label(__('Student'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn (Registration $record): ?string => $record->student?->studentProfile?->student_number)
                    ->url(fn (Registration $record): ?string => $record->student_id && auth()->user()->can('Student Details List')
                        ? route('students.details', $record->student_id)
                        : null),

                TextColumn::make('student.email')
                    ->label(__('Email'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('student.phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->placeholder('---')
                    ->toggleable(),

                TextColumn::make('student.studentProfile.major.name')
                    ->label(__('Major'))
                    ->placeholder('---')
                    ->toggleable(),

                TextColumn::make('supervisor.name')
                    ->label(__('Practical Training Supervisor'))
                    ->searchable()
                    ->toggleable()
                    ->visible(fn (): bool => ! $this->shouldScopeToAuthenticatedSupervisor()),

                TextColumn::make('course.name')
                    ->label(__('Course'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('studentCompany.company.name')
                    ->label(__('Company'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'studentCompany.company.translations',
                        fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                    ))
                    ->placeholder('---')
                    ->color('primary')
                    ->url(fn (Registration $record): ?string => $record->studentCompany?->company_id && auth()->user()->can('Company Details List')
                        ? route('companies.details', $record->studentCompany->company_id)
                        : null),

                TextColumn::make('studentCompany.branch.name')
                    ->label(__('Branch'))
                    ->placeholder('---')
                    ->toggleable(),

                TextColumn::make('studentCompany.department.name')
                    ->label(__('Department'))
                    ->placeholder('---')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('studentCompany.status')
                    ->label(__('Training Status'))
                    ->badge()
                    ->placeholder('---'),

                TextColumn::make('semester')
                    ->label(__('Semester'))
                    ->badge()
                    ->toggleable(),

                TextColumn::make('year')
                    ->label(__('Year'))
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(6)
            ->actions($this->getTableActions())
            ->bulkActions([]);
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('student_number')
                ->label(__('Student Number'))
                ->form([
                    TextInput::make('student_number')
                        ->label(__('Student Number'))
                        ->prefixIcon('solar-user-id-bold-duotone')
                        ->live(debounce: 500),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['student_number'],
                        fn (Builder $query, string $studentNumber): Builder => $query->whereHas(
                            'student.studentProfile',
                            fn (Builder $query): Builder => $query->where('student_number', 'like', "%{$studentNumber}%")
                        )
                    );
                }),

            SelectFilter::make('supervisor_id')
                ->label(__('Practical Training Supervisor'))
                ->options(fn (): array => User::role(UserRole::PRACTICAL_TRAINING_SUPERVISOR->value)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload()
                ->visible(fn (): bool => ! $this->shouldScopeToAuthenticatedSupervisor()),

            SelectFilter::make('course_id')
                ->label(__('Course'))
                ->options(fn (): array => Course::query()
                    ->orderBy('course_code')
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload(),

            SelectFilter::make('company')
                ->label(__('Company'))
                ->options(fn (): array => Company::query()
                    ->with('translations')
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray())
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['value'],
                        fn (Builder $query, int|string $companyId): Builder => $query->whereHas(
                            'studentCompany',
                            fn (Builder $query): Builder => $query->where('company_id', $companyId)
                        )
                    );
                })
                ->searchable()
                ->preload(),

            SelectFilter::make('training_status')
                ->label(__('Training Status'))
                ->options(TrainingStatus::options())
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['value'],
                        fn (Builder $query, int|string $status): Builder => $query->whereHas(
                            'studentCompany',
                            fn (Builder $query): Builder => $query->where('status', $status)
                        )
                    );
                })
                ->native(false),

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
                        fn (Builder $query, int|string $year): Builder => $query->where('year', $year)
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
                        fn (Builder $query, int|string $semesterType): Builder => $query->where('semester', $semesterType)
                    );
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn (): bool => auth()->user()->can('PracticalSupervisorStudent Info')),

            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->form(fn (Registration $record): array => [
                    Grid::make(2)->schema([
                        TextInput::make('student_name')
                            ->label(__('Student'))
                            ->default($record->student?->name)
                            ->disabled()
                            ->prefixIcon('solar-user-id-bold-duotone'),

                        TextInput::make('student_number')
                            ->label(__('Student Number'))
                            ->default($record->student?->studentProfile?->student_number)
                            ->disabled()
                            ->prefixIcon('solar-hashtag-square-bold-duotone'),

                        TextInput::make('supervisor_name')
                            ->label(__('Practical Training Supervisor'))
                            ->default($record->supervisor?->name)
                            ->disabled()
                            ->prefixIcon('solar-user-speak-rounded-bold-duotone'),

                        TextInput::make('course_name')
                            ->label(__('Course'))
                            ->default($record->course?->name)
                            ->disabled()
                            ->prefixIcon('solar-book-bold-duotone'),

                        TextInput::make('company_name')
                            ->label(__('Company'))
                            ->default($record->studentCompany?->company?->name)
                            ->disabled()
                            ->prefixIcon('solar-city-bold-duotone'),

                        TextInput::make('branch_name')
                            ->label(__('Branch'))
                            ->default($record->studentCompany?->branch?->name)
                            ->disabled()
                            ->prefixIcon('solar-map-point-bold-duotone'),

                        TextInput::make('semester')
                            ->label(__('Semester'))
                            ->default($record->semester?->getLabel())
                            ->disabled()
                            ->prefixIcon('solar-calendar-bold-duotone'),

                        TextInput::make('year')
                            ->label(__('Year'))
                            ->default($record->year)
                            ->disabled()
                            ->prefixIcon('solar-calendar-search-bold-duotone'),
                    ]),
                ])
                ->modalSubmitAction(false)
                ->visible(fn (): bool => auth()->user()->can('PracticalSupervisorStudent View')),
        ];
    }

    protected function supervisedRegistrationsQuery(): Builder
    {
        return Registration::query()
            ->whereHas(
                'supervisor.roles',
                fn (Builder $query): Builder => $query->where('name', UserRole::PRACTICAL_TRAINING_SUPERVISOR->value)
            )
            ->when(
                $this->shouldScopeToAuthenticatedSupervisor(),
                fn (Builder $query): Builder => $query->where('supervisor_id', auth()->id())
            );
    }

    protected function shouldScopeToAuthenticatedSupervisor(): bool
    {
        $user = auth()->user();

        return (bool) (
            $user?->hasRole(UserRole::PRACTICAL_TRAINING_SUPERVISOR->value)
            && ! $user?->hasAnyRole([
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN->value,
            ])
        );
    }

    public function render()
    {
        return view('ppuds::livewire.pages.practical-supervisor-student.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Practical Supervisor Students'), 'url' => route('practical-supervisor-students.index')],
            ],
        ]);
    }
}

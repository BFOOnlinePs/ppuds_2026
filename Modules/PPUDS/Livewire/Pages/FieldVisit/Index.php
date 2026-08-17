<?php

namespace Modules\PPUDS\Livewire\Pages\FieldVisit;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Maatwebsite\Excel\Excel as WriterType;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Exports\FieldVisitsExport;
use Modules\PPUDS\Support\HasSupervisorFilter;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable {
        updatedTableFilters as baseUpdatedTableFilters;
    }
    use HasSupervisorFilter;
    use ScopesStudentCompanyVisibility;

    public function updatedTableFilters(): void
    {
        $this->baseUpdatedTableFilters();

        // The table (query/columns/actions) is built once during Livewire's
        // hydrate/booted phase, which runs BEFORE this filter update is applied
        // to $this->tableFilters. Since switching "not visited" mode swaps the
        // entire query/columns/actions (not just a where clause), we must
        // rebuild the table here so it reflects the freshly toggled state
        // before rendering — otherwise the toggle appears to do nothing until
        // a later, unrelated interaction.
        $this->bootedInteractsWithTable();
    }

    public function table(Table $table): Table
    {
        $showingUnvisited = $this->isShowingUnvisitedStudents();

        return $table
            ->query(fn () => $showingUnvisited ? $this->unvisitedStudentsQuery() : $this->fieldVisitsQuery())
            ->columns($showingUnvisited ? $this->unvisitedStudentColumns() : $this->fieldVisitColumns())
            ->filters($this->getTableFilters($showingUnvisited), layout: \Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->actions($showingUnvisited ? [] : $this->getTableActions())
            ->headerActions([
                Action::make('export_field_visits')
                    ->label(__('Export Field Visits'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => app(ExcelServiceInterface::class)->download(
                        new FieldVisitsExport($this->getTableQueryForExport()),
                        $this->exportFilename(),
                        WriterType::XLSX
                    ))
                    ->visible(fn () => ! $showingUnvisited && auth()->user()->can('FieldVisit View List')),

                \Modules\Core\Filament\Forms\Components\CreateAction::make('create')
                    ->label(__('Add Field Visit'))
                    ->url(route('field-visits.add'))
                    ->visible(fn () => auth()->user()->can('FieldVisit Create')),
            ])
            ->bulkActions($showingUnvisited ? [] : $this->getTableBulkAction());
    }

    protected function fieldVisitsQuery(): Builder
    {
        return FieldVisit::query()
            ->with(['studentCompany.registration.student', 'supervisor'])
            ->whereHas(
                'studentCompany',
                fn (Builder $studentCompanyQuery): Builder => $this->applyStudentCompanyVisibilityScope($studentCompanyQuery)
            );
    }

    protected function unvisitedStudentsQuery(): Builder
    {
        return StudentCompany::query()
            ->with([
                'student.studentProfile',
                'company.translations',
                'branch.translations',
                'department.translations',
                'registration.supervisor',
            ])
            ->whereDoesntHave('fieldVisits')
            ->tap(fn (Builder $query): Builder => $this->applyStudentCompanyVisibilityScope($query));
    }

    protected function fieldVisitColumns(): array
    {
        return [
            TextColumn::make('studentCompany.registration.student.name')
                ->label(__('Student'))
                ->searchable()
                ->sortable()
                ->weight('bold')
                ->color('primary')
                ->description(fn (FieldVisit $record) => $record->studentCompany?->registration?->student?->email)
                ->url(fn (FieldVisit $record) => $this->fieldVisitStudentDetailsUrl($record)),

            TextColumn::make('supervisor.name')
                ->label(__('Supervisor'))
                ->searchable()
                ->sortable()
                ->icon('solar-user-id-bold-duotone'),

            TextColumn::make('visiting_place')
                ->label(__('Visiting Place'))
                ->searchable()
                ->limit(30)
                ->icon('solar-map-point-bold-duotone'),

            TextColumn::make('visit_date')
                ->label(__('Visit Date'))
                ->date('Y-m-d')
                ->sortable()
                ->icon('solar-calendar-date-bold-duotone'),

            TextColumn::make('visit_time')
                ->label(__('Visit Time'))
                ->time('H:i')
                ->icon('solar-clock-circle-bold-duotone'),

            TextColumn::make('visit_duration')
                ->label(__('Duration (Mins)'))
                ->numeric()
                ->sortable()
                ->suffix(' '.__('Mins')),

            TextColumn::make('created_at')
                ->label(__('Created At'))
                ->dateTime('Y-m-d')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function unvisitedStudentColumns(): array
    {
        return [
            TextColumn::make('student.studentProfile.student_number')
                ->label(__('Student Number'))
                ->weight('bold')
                ->searchable(),

            TextColumn::make('student.name')
                ->label(__('Student'))
                ->searchable()
                ->sortable()
                ->weight('bold')
                ->color('primary')
                ->description(fn (StudentCompany $record) => $record->student?->email)
                ->url(fn (StudentCompany $record) => $this->unvisitedStudentDetailsUrl($record)),

            TextColumn::make('company.name')
                ->label(__('Company'))
                ->icon('solar-buildings-bold-duotone'),

            TextColumn::make('branch.name')
                ->label(__('Branch'))
                ->toggleable(),

            TextColumn::make('department.name')
                ->label(__('Department'))
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('registration.supervisor.name')
                ->label(__('Supervisor'))
                ->icon('solar-user-id-bold-duotone'),

            TextColumn::make('registration.semester')
                ->label(__('Semester'))
                ->badge()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('registration.year')
                ->label(__('Year'))
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function isShowingUnvisitedStudents(): bool
    {
        return (bool) data_get($this->getTableFilterState('not_visited'), 'not_visited_toggle', false);
    }

    protected function fieldVisitStudentDetailsUrl(FieldVisit $record): ?string
    {
        $student = $record->studentCompany?->registration?->student;

        if (! $student || ! auth()->user()?->can('Student Details List')) {
            return null;
        }

        return route('students.details', $student->id);
    }

    protected function unvisitedStudentDetailsUrl(StudentCompany $record): ?string
    {
        $student = $record->student;

        if (! $student || ! auth()->user()?->can('Student Details List')) {
            return null;
        }

        return route('students.details', $student->id);
    }

    protected function getTableFilters(bool $showingUnvisited): array
    {
        return [
            \Filament\Tables\Filters\Filter::make('not_visited')
                ->label(__('Students Not Visited'))
                ->form([
                    Toggle::make('not_visited_toggle')
                        ->label(__('Show Students Not Visited Yet'))
                        ->live(),
                ])
                ->query(fn (Builder $query): Builder => $query)
                ->indicateUsing(fn (array $data): array => filled($data['not_visited_toggle'] ?? null) && $data['not_visited_toggle']
                    ? [\Filament\Tables\Filters\Indicator::make(__('Students Not Visited'))->removeField('not_visited_toggle')]
                    : []),

            \Filament\Tables\Filters\SelectFilter::make('student_id')
                ->label(__('Student'))
                ->options(fn (): array => $this->studentOptions())
                ->query(function (Builder $query, array $data) use ($showingUnvisited): Builder {
                    return $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $studentId): Builder => $showingUnvisited
                            ? $query->where('student_id', (int) $studentId)
                            : $query->whereHas(
                                'studentCompany',
                                fn (Builder $studentCompanyQuery): Builder => $studentCompanyQuery->where('student_id', (int) $studentId)
                            )
                    );
                })
                ->searchable()
                ->preload(),

            \Filament\Tables\Filters\SelectFilter::make('supervisor_id')
                ->label(__('Supervisor'))
                ->options(fn (): array => $this->supervisorOptions())
                ->query(function (Builder $query, array $data) use ($showingUnvisited): Builder {
                    if ($this->shouldLockSupervisorFilter()) {
                        return $query;
                    }

                    return $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $supervisorId): Builder => $query->whereHas(
                            $showingUnvisited ? 'registration' : 'studentCompany.registration',
                            fn (Builder $registrationQuery): Builder => $registrationQuery->where('supervisor_id', $supervisorId)
                        )
                    );
                })
                ->searchable()
                ->preload()
                ->visible(fn (): bool => ! $this->shouldLockSupervisorFilter()),

            \Filament\Tables\Filters\SelectFilter::make('company')
                ->label(__('Company'))
                ->options(fn (): array => $this->companyOptions())
                ->query(function (Builder $query, array $data) use ($showingUnvisited): Builder {
                    return $query->when($data['value'], function (Builder $query, $companyId) use ($showingUnvisited) {
                        return $showingUnvisited
                            ? $query->where('company_id', $companyId)
                            : $query->whereHas('studentCompany', fn (Builder $scQuery) => $scQuery->where('company_id', $companyId));
                    });
                })
                ->searchable()
                ->preload(),

            \Filament\Tables\Filters\Filter::make('year')
                ->form([
                    TextInput::make('year')
                        ->label(__('Academic Year'))
                        ->numeric()
                        ->default(app(\Modules\PPUDS\Settings\GeneralSettings::class)->year)
                        ->placeholder(date('Y'))
                        ->prefixIcon('solar-calendar-search-bold-duotone'),
                ])
                ->query(function (Builder $query, array $data) use ($showingUnvisited): Builder {
                    return $query->when(
                        $data['year'],
                        fn (Builder $query, $year): Builder => $query->whereHas(
                            $showingUnvisited ? 'registration' : 'studentCompany.registration',
                            fn (Builder $regQuery) => $regQuery->where('year', $year)
                        )
                    );
                }),

            \Filament\Tables\Filters\Filter::make('semester_type')
                ->form([
                    \Filament\Forms\Components\Select::make('semester_type')
                        ->label(__('Semester Type'))
                        ->options(SemesterType::options())
                        ->default(app(\Modules\PPUDS\Settings\GeneralSettings::class)->semester_type->value)
                        ->prefixIcon('solar-bookmark-circle-bold-duotone'),
                ])
                ->query(function (Builder $query, array $data) use ($showingUnvisited): Builder {
                    return $query->when(
                        $data['semester_type'],
                        fn (Builder $query, $semester_type): Builder => $query->whereHas(
                            $showingUnvisited ? 'registration' : 'studentCompany.registration',
                            fn (Builder $regQuery) => $regQuery->where('semester', $semester_type)
                        )
                    );
                }),
        ];
    }

    protected function exportFilename(): string
    {
        return 'field-visits-'.now()->format('Y-m-d-His').'.xlsx';
    }

    protected function shouldLockSupervisorFilter(): bool
    {
        return $this->shouldScopeUniversitySupervisorStudentCompanies();
    }

    protected function supervisorOptions(): array
    {
        if ($this->shouldLockSupervisorFilter()) {
            return User::query()
                ->whereKey(auth()->id())
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }

        return $this->supervisorFilterOptions();
    }

    protected function studentOptions(): array
    {
        return StudentCompany::query()
            ->with('student:id,name')
            ->tap(fn (Builder $query): Builder => $this->applyStudentCompanyVisibilityScope($query))
            ->whereHas('student')
            ->get()
            ->mapWithKeys(fn (StudentCompany $studentCompany): array => [
                $studentCompany->student_id => $studentCompany->student?->name,
            ])
            ->filter()
            ->sort()
            ->toArray();
    }

    protected function companyOptions(): array
    {
        return Company::query()
            ->tap(fn (Builder $query): Builder => $this->applyCompanyVisibilityScope($query))
            ->get()
            ->pluck('name', 'id')
            ->sort()
            ->toArray();
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn () => auth()->user()->can('FieldVisit Info')),

            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->form(fn (FieldVisit $record) => [
                    Grid::make(2)->schema([
                        TextInput::make('student_name')
                            ->label(__('Student'))
                            ->default($record->studentCompany?->registration?->student?->name)
                            ->disabled()
                            ->prefixIcon('solar-user-id-bold-duotone'),

                        TextInput::make('supervisor_name')
                            ->label(__('Supervisor'))
                            ->default($record->supervisor?->name)
                            ->disabled()
                            ->prefixIcon('solar-user-id-bold-duotone'),

                        TextInput::make('visiting_place')
                            ->label(__('Visiting Place'))
                            ->default($record->visiting_place)
                            ->disabled()
                            ->prefixIcon('solar-map-point-bold-duotone'),

                        DatePicker::make('visit_date')
                            ->label(__('Visit Date'))
                            ->default($record->visit_date)
                            ->disabled()
                            ->prefixIcon('solar-calendar-date-bold-duotone'),

                        TimePicker::make('visit_time')
                            ->label(__('Visit Time'))
                            ->default($record->visit_time)
                            ->disabled()
                            ->prefixIcon('solar-clock-circle-bold-duotone'),

                        TextInput::make('visit_duration')
                            ->label(__('Duration'))
                            ->default($record->visit_duration)
                            ->disabled()
                            ->suffix(__('Minutes')),

                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->default($record->notes)
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
                ])
                ->modalSubmitAction(false)
                ->visible(fn () => auth()->user()->can('FieldVisit View')),

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn (FieldVisit $record) => route('field-visits.edit', $record->id))
                ->visible(fn () => auth()->user()->can('FieldVisit Update')),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function ($record) {
                    $record->delete();
                    Toaster::success(__('Field visit record deleted successfully'));
                })
                ->visible(fn () => auth()->user()->can('FieldVisit Delete')),
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
                    ->action(function (Collection $records) {
                        abort_unless(auth()->user()?->can('FieldVisit Delete'), 403);

                        $records->each->delete();
                    })
                    ->after(fn () => Toaster::success(__('Selected records deleted successfully')))
                    ->visible(fn () => auth()->user()->can('FieldVisit Delete')),
            ]),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.field-visit.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Field Visits'), 'url' => route('field-visits.index')],
            ],
        ]);
    }
}

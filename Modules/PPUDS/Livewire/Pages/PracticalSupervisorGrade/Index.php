<?php

namespace Modules\PPUDS\Livewire\Pages\PracticalSupervisorGrade;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\HasSupervisorFilter;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

/**
 * شاشة وضع علامة مشرف الجامعة. تعمل بنفس منطق شاشة طلاب مشرف التقييم،
 * لكنها تكتب في supervisor_score وتُقصَر على طلاب المشرف نفسه.
 */
class Index extends Component implements HasForms, HasTable
{
    use HasSupervisorFilter;
    use InteractsWithForms;
    use InteractsWithTable;
    use ScopesStudentCompanyVisibility;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->supervisedPlacementsQuery()
                ->with([
                    'student.studentProfile.major',
                    'registration.supervisor',
                    'company',
                    'branch',
                    'department',
                ]))
            ->columns([
                UserColumn::make('student.name')
                    ->label(__('Student'))
                    ->user(fn (StudentCompany $record) => $record->student)
                    ->subtitle(fn (StudentCompany $record): ?string => $record->student?->studentProfile?->student_number)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('student.studentProfile.major.name')
                    ->label(__('Major'))
                    ->placeholder('---')
                    ->toggleable(),

                TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'company.translations',
                        fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                    ))
                    ->placeholder('---'),

                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->placeholder('---')
                    ->toggleable(),

                TextColumn::make('department.name')
                    ->label(__('Department'))
                    ->placeholder('---')
                    ->toggleable(isToggledHiddenByDefault: true),

                UserColumn::make('registration.supervisor.name')
                    ->label(__('Practical Training Supervisor'))
                    ->user(fn (StudentCompany $record) => $record->registration?->supervisor)
                    ->linksToSupervisor()
                    ->toggleable()
                    ->visible(fn (): bool => ! $this->shouldScopeUniversitySupervisorStudentCompanies()),

                TextColumn::make('supervisor_score')
                    ->label(fn (): string => __('Grade (out of :max)', ['max' => $this->maxGrade()]))
                    ->badge()
                    ->color(fn (?int $state): string => $state === null ? 'gray' : 'success')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? __('Not graded yet')
                        : $state.' / '.$this->maxGrade()),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
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

            $this->supervisorSelectFilter('registration')
                ->label(__('Practical Training Supervisor'))
                ->visible(fn (): bool => ! $this->shouldScopeUniversitySupervisorStudentCompanies()),

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
                        fn (Builder $query, int|string $year): Builder => $query->whereHas(
                            'registration',
                            fn (Builder $query): Builder => $query->where('year', $year)
                        )
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
                        fn (Builder $query, int|string $semesterType): Builder => $query->whereHas(
                            'registration',
                            fn (Builder $query): Builder => $query->where('semester', $semesterType)
                        )
                    );
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('grade')
                ->label(fn (StudentCompany $record): string => $record->supervisor_score === null ? __('Set Grade') : __('Grade'))
                ->icon('heroicon-o-star')
                ->color('primary')
                ->form(fn (): array => [
                    TextInput::make('supervisor_score')
                        ->label(__('Grade (out of :max)', ['max' => $this->maxGrade()]))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue($this->maxGrade())
                        ->required(),
                ])
                ->fillForm(fn (StudentCompany $record): array => [
                    'supervisor_score' => $record->supervisor_score,
                ])
                ->modalHeading(fn (): string => __('Grade (out of :max)', ['max' => $this->maxGrade()]))
                ->modalSubmitActionLabel(__('Save'))
                ->visible(fn (): bool => auth()->user()->can('PracticalSupervisorStudent Grade'))
                ->action(function (StudentCompany $record, array $data): void {
                    abort_unless(auth()->user()?->can('PracticalSupervisorStudent Grade'), 403);

                    $record->update([
                        'supervisor_score' => min((int) $data['supervisor_score'], $this->maxGrade()),
                    ]);

                    Toaster::success(__('Grade saved successfully'));
                }),
        ];
    }

    /** العلامة القصوى لمشرف الجامعة كما هي محددة في الإعدادات. */
    protected function maxGrade(): int
    {
        return app(GeneralSettings::class)->university_supervisor_max_grade;
    }

    protected function supervisedPlacementsQuery(): Builder
    {
        return StudentCompany::query()
            ->whereHas(
                'registration.supervisor.roles',
                fn (Builder $query): Builder => $query->where('name', UserRole::PRACTICAL_TRAINING_SUPERVISOR->value)
            )
            ->tap(fn (Builder $query) => $this->applyUniversitySupervisorStudentCompanyScope($query));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.practical-supervisor-grade.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('University Supervisor Grades'), 'url' => route('practical-supervisor-grades.index')],
            ],
        ]);
    }
}

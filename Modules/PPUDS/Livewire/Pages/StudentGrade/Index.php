<?php

namespace Modules\PPUDS\Livewire\Pages\StudentGrade;

use App\View\Components\AppLayout;
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
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\HasSupervisorFilter;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

/**
 * شاشة عرض علامات الطلاب مجتمعة: علامة مشرف التقييم، وعلامة مشرف الجامعة،
 * وعلامة الشركة، والمجموع. للعرض فقط — لا يوجد فيها أي تعديل، فوضع العلامات
 * يتم من شاشتَي مشرف التقييم ومشرف الجامعة.
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
            ->query(fn () => StudentCompany::query()
                ->with([
                    'student.studentProfile.major',
                    'evaluationSupervisor',
                    'registration.supervisor',
                    'company',
                    'branch',
                ])
                ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query)))
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
                    ->url(fn (StudentCompany $record): ?string => $record->company_id && auth()->user()->can('Company Details List')
                        ? route('companies.details', $record->company_id)
                        : null)
                    ->color('primary')
                    ->placeholder('---'),

                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->placeholder('---')
                    ->toggleable(isToggledHiddenByDefault: true),

                UserColumn::make('evaluationSupervisor.name')
                    ->label(__('Evaluation Supervisor'))
                    ->user(fn (StudentCompany $record) => $record->evaluationSupervisor)
                    ->linksToSupervisor()
                    ->toggleable(isToggledHiddenByDefault: true),

                UserColumn::make('registration.supervisor.name')
                    ->label(__('Practical Training Supervisor'))
                    ->user(fn (StudentCompany $record) => $record->registration?->supervisor)
                    ->linksToSupervisor()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('evaluation_score')
                    ->label(__('Evaluation Supervisor Grade'))
                    ->badge()
                    ->color(fn (?int $state): string => $state === null ? 'gray' : 'success')
                    ->formatStateUsing(fn (?int $state): string => $this->formatGrade($state, $this->evaluationMaxGrade())),

                TextColumn::make('supervisor_score')
                    ->label(__('University Supervisor Grade'))
                    ->badge()
                    ->color(fn (?int $state): string => $state === null ? 'gray' : 'success')
                    ->formatStateUsing(fn (?int $state): string => $this->formatGrade($state, $this->universityMaxGrade())),

                TextColumn::make('registration.company_score')
                    ->label(__('Company Grade'))
                    ->badge()
                    ->color(fn (?float $state): string => $state === null ? 'gray' : 'success')
                    ->formatStateUsing(fn (?float $state): string => $this->formatGrade($state, $this->companyMaxGrade())),

                TextColumn::make('total_grade')
                    ->label(__('Total Grade'))
                    ->badge()
                    ->color(fn (StudentCompany $record): string => $this->totalGrade($record) === null ? 'gray' : 'primary')
                    ->weight('bold')
                    ->getStateUsing(fn (StudentCompany $record): string => $this->formatGrade(
                        $this->totalGrade($record),
                        $this->totalMaxGrade()
                    )),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([])
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

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => $this->applyCompanyVisibilityScope(Company::query())
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload(),

            $this->supervisorSelectFilter('registration')
                ->label(__('Practical Training Supervisor')),

            SelectFilter::make('evaluation_supervisor_id')
                ->label(__('Evaluation Supervisor'))
                ->options(fn (): array => User::role(UserRole::EVALUATION_SUPERVISOR->value)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload(),

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

    /**
     * مجموع العلامات الثلاث. يبقى فارغاً ما لم تُرصد علامة واحدة على الأقل،
     * حتى لا يظهر صفر لطالب لم يُقيَّم بعد.
     */
    protected function totalGrade(StudentCompany $record): int|float|null
    {
        $scores = [
            $record->evaluation_score,
            $record->supervisor_score,
            $record->registration?->company_score,
        ];

        $recorded = array_filter($scores, fn ($score): bool => $score !== null);

        return $recorded === [] ? null : array_sum($recorded);
    }

    protected function formatGrade(int|float|null $score, int $maxGrade): string
    {
        if ($score === null) {
            return __('Not graded yet');
        }

        return ((float) $score == (int) $score ? (int) $score : round((float) $score, 2)).' / '.$maxGrade;
    }

    protected function evaluationMaxGrade(): int
    {
        return app(GeneralSettings::class)->evaluation_supervisor_max_grade;
    }

    protected function universityMaxGrade(): int
    {
        return app(GeneralSettings::class)->university_supervisor_max_grade;
    }

    protected function companyMaxGrade(): int
    {
        return app(GeneralSettings::class)->company_max_grade;
    }

    protected function totalMaxGrade(): int
    {
        return $this->evaluationMaxGrade() + $this->universityMaxGrade() + $this->companyMaxGrade();
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student-grade.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Student Grades'), 'url' => route('student-grades.index')],
            ],
        ]);
    }
}

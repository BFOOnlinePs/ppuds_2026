<?php

namespace Modules\PPUDS\Livewire\Pages\EvaluationSupervisor\Details\StudentCompany;

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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings;

/**
 * الطلاب المسندون لمشرف تقييم واحد مع علامة التقييم لكل تدريب.
 * يُعرض كتبويب في سجل مشرف التقييم.
 */
class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?int $evaluationSupervisorId = null;

    public function mount(?int $evaluationSupervisorId = null)
    {
        $this->evaluationSupervisorId = $evaluationSupervisorId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->assignedPlacementsQuery()
                ->with([
                    'student.studentProfile.major',
                    'student.media',
                    'registration.course',
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
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'company.translations',
                        fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                    ))
                    ->placeholder('—')
                    ->color('primary')
                    ->url(fn (StudentCompany $record): ?string => $record->company_id && auth()->user()->can('Company Details List') ? route('companies.details', $record->company_id) : null),

                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('department.name')
                    ->label(__('Department'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registration.course.name')
                    ->label(__('Course'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('registration.semester')
                    ->label(__('Semester'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registration.year')
                    ->label(__('Year'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('evaluation_score')
                    ->label(fn (): string => __('Grade (out of :max)', ['max' => $this->maxGrade()]))
                    ->alignCenter()
                    ->badge()
                    ->sortable()
                    ->color(fn (?int $state): string => $state === null ? 'gray' : 'success')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? __('Not graded yet')
                        : $state.' / '.$this->maxGrade()),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->actions($this->getTableActions())
            ->bulkActions([])
            ->emptyStateHeading(__('No students assigned yet'))
            ->emptyStateDescription(__('Assign students to this supervisor from the Student Companies list using “Assign Evaluation Supervisor”.'))
            ->emptyStateActions([
                Action::make('assignStudents')
                    ->label(__('Student Companies List'))
                    ->icon('solar-clipboard-list-bold-duotone')
                    ->url(route('student-companies.index'))
                    ->visible(fn (): bool => auth()->user()->can('StudentCompany View List')
                        && auth()->user()->can('StudentCompany Assign Evaluation Supervisor')),
            ]);
    }

    protected function getTableFilters(): array
    {
        return [
            TernaryFilter::make('grade_status')
                ->label(__('Grading Status'))
                ->placeholder(__('All'))
                ->trueLabel(__('Graded'))
                ->falseLabel(__('Not graded yet'))
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('evaluation_score'),
                    false: fn (Builder $query): Builder => $query->whereNull('evaluation_score'),
                    blank: fn (Builder $query): Builder => $query,
                ),

            SelectFilter::make('status')
                ->label(__('Training Status'))
                ->options(TrainingStatus::class)
                ->native(false),

            // الشركات التي فيها طلاب هذا المشرف فقط، لا كل الشركات في النظام.
            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => Company::query()
                    ->with('translations')
                    ->whereIn('id', $this->assignedPlacementsQuery()->whereNotNull('company_id')->select('company_id'))
                    ->get()
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
                        ->placeholder((string) app(GeneralSettings::class)->year),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['year'],
                        fn (Builder $query, $year) => $query->whereHas('registration', fn ($query) => $query->where('year', $year))
                    );
                }),

            Filter::make('semester_type')
                ->form([
                    Select::make('semester_type')
                        ->label(__('Semester Type'))
                        ->options(SemesterType::options()),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['semester_type'],
                        fn (Builder $query, $semesterType) => $query->whereHas('registration', fn ($query) => $query->where('semester', $semesterType))
                    );
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('details')
                ->label('')
                ->tooltip(__('View Details'))
                ->icon('solar-eye-bold-duotone')
                ->color('gray')
                ->size('xl')
                ->url(fn (StudentCompany $record): string => route('student-companies.details', $record->id))
                ->visible(fn (): bool => auth()->user()->can('StudentCompany Details')),
        ];
    }

    /** العلامة القصوى لمشرف التقييم كما هي محددة في الإعدادات. */
    protected function maxGrade(): int
    {
        return app(GeneralSettings::class)->evaluation_supervisor_max_grade;
    }

    protected function assignedPlacementsQuery(): Builder
    {
        return StudentCompany::query()
            ->when(
                $this->evaluationSupervisorId,
                fn (Builder $query) => $query->where('evaluation_supervisor_id', $this->evaluationSupervisorId),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            );
    }

    // يُعرض داخل سجل مشرف التقييم فقط وليس له مسار خاص، فلا يحتاج تخطيط صفحة.
    public function render()
    {
        return view('ppuds::livewire.pages.evaluation-supervisor.details.student-company.index');
    }
}

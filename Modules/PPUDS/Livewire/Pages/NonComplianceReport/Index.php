<?php

namespace Modules\PPUDS\Livewire\Pages\NonComplianceReport;

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
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Services\NonComplianceReportService;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\HasSupervisorFilter;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HasSupervisorFilter;
    use ScopesStudentCompanyVisibility;

    private ?array $nonCompliantStudentCompanyIds = null;

    private array $nonComplianceSummaries = [];

    public function table(Table $table): Table
    {
        $studentCompanyTable = (new StudentCompany())->getTable();

        return $table
            ->query(fn (): Builder => StudentCompany::query()
                ->with([
                    'attendances',
                    'branch.workingHours',
                    'company',
                    'leaveRequests',
                    'registration',
                    'student.studentProfile',
                ])
                ->whereIn("{$studentCompanyTable}.id", $this->nonCompliantStudentCompanyIds()))
            ->columns([
                TextColumn::make('student.studentProfile.student_number')
                    ->label(__('Student Number'))
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('student.name')
                    ->label(__('Student Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'company.translations',
                        fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                    )),

                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->toggleable(),

                TextColumn::make('total_non_compliance_days')
                    ->label(__('Total Non Compliance Days'))
                    ->getStateUsing(fn (StudentCompany $record): int => (int) $this->summaryValue($record, 'total_non_compliance_days'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->wrapHeader(),

                TextColumn::make('total_absence_days')
                    ->label(__('Total Absence Days'))
                    ->getStateUsing(fn (StudentCompany $record): int => (int) $this->summaryValue($record, 'total_absence_days'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->wrapHeader(),

                TextColumn::make('unexcused_absence_days')
                    ->label(__('Unexcused Absence Days'))
                    ->getStateUsing(fn (StudentCompany $record): int => (int) $this->summaryValue($record, 'unexcused_absence_days'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->wrapHeader(),

                TextColumn::make('late_days')
                    ->label(__('Late Days'))
                    ->getStateUsing(fn (StudentCompany $record): int => (int) $this->summaryValue($record, 'late_days'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('total_late_hours')
                    ->label(__('Total Late Hours'))
                    ->getStateUsing(fn (StudentCompany $record): float => (float) $this->summaryValue($record, 'total_late_hours'))
                    ->numeric(decimalPlaces: 2)
                    ->wrapHeader(),

                TextColumn::make('max_late_hours')
                    ->label(__('Max Late Hours'))
                    ->getStateUsing(fn (StudentCompany $record): float => (float) $this->summaryValue($record, 'max_late_hours'))
                    ->numeric(decimalPlaces: 2)
                    ->wrapHeader(),

                TextColumn::make('last_late_date')
                    ->label(__('Last Late Date'))
                    ->getStateUsing(fn (StudentCompany $record): ?string => $this->summaryValue($record, 'last_late_date'))
                    ->placeholder('-')
                    ->wrapHeader(),

                TextColumn::make('last_late_duration')
                    ->label(__('Last Late Duration'))
                    ->getStateUsing(fn (StudentCompany $record): ?string => $this->summaryValue($record, 'last_late_duration'))
                    ->placeholder('-')
                    ->wrapHeader(),

                TextColumn::make('registration.semester')
                    ->label(__('Semester'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registration.year')
                    ->label(__('Year'))
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    TextInput::make('number')
                        ->label(__('Number / Name'))
                        ->live(debounce: 500),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if (! empty($data['number'])) {
                        $query->where(function (Builder $query) use ($data): void {
                            $query->whereHas(
                                'student.studentProfile',
                                fn (Builder $studentProfileQuery) => $studentProfileQuery->where('student_number', 'like', "%{$data['number']}%")
                            )->orWhereHas(
                                'student',
                                fn (Builder $studentQuery) => $studentQuery->where('name', 'like', "%{$data['number']}%")
                            );
                        });
                    }

                    return $query;
                }),

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => $this->applyCompanyVisibilityScope(Company::query())
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload(),

            Filter::make('minimum_late_hours')
                ->label(__('Minimum Late Hours'))
                ->form([
                    TextInput::make('hours')
                        ->label(__('Late Hours'))
                        ->numeric()
                        ->minValue(0)
                        ->step('0.25')
                        ->live(debounce: 500),
                ])
                ->query(fn (Builder $query, array $data): Builder => $this->applyMinimumLateHoursFilter($query, $data)),

            $this->supervisorSelectFilter('registration'),

            Filter::make('year')
                ->form([
                    TextInput::make('year')
                        ->label(__('Academic Year'))
                        ->numeric()
                        ->default(app(GeneralSettings::class)->year)
                        ->placeholder(date('Y')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['year'] ?? null,
                        fn (Builder $query, $year) => $query->whereHas('registration', fn (Builder $registrationQuery) => $registrationQuery->where('year', $year))
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
                        $data['semester_type'] ?? null,
                        fn (Builder $query, $semesterType) => $query->whereHas('registration', fn (Builder $registrationQuery) => $registrationQuery->where('semester', $semesterType))
                    );
                }),
        ];
    }

    private function applyMinimumLateHoursFilter(Builder $query, array $data): Builder
    {
        if (! is_numeric($data['hours'] ?? null)) {
            return $query;
        }

        return app(NonComplianceReportService::class)->applyMinimumLateHoursFilter($query, $data['hours']);
    }

    private function nonCompliantStudentCompanyIds(): array
    {
        if ($this->nonCompliantStudentCompanyIds !== null) {
            return $this->nonCompliantStudentCompanyIds;
        }

        $settings = app(GeneralSettings::class);

        $this->nonCompliantStudentCompanyIds = StudentCompany::query()
            ->with([
                'attendances',
                'branch.workingHours',
                'leaveRequests',
                'registration',
            ])
            ->whereHas('registration', fn (Builder $query) => $query
                ->where('year', $settings->year)
                ->where('semester', $settings->semester_type->value))
            ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query))
            ->get()
            ->filter(fn (StudentCompany $studentCompany): bool => $this->summaryValue($studentCompany, 'total_absence_days') > 0
                || $this->summaryValue($studentCompany, 'late_days') > 0)
            ->pluck('id')
            ->values()
            ->all();

        return $this->nonCompliantStudentCompanyIds;
    }

    private function summaryValue(StudentCompany $studentCompany, string $key): int|float|string|null
    {
        $summary = $this->summary($studentCompany);

        return array_key_exists($key, $summary) ? $summary[$key] : 0;
    }

    private function summary(StudentCompany $studentCompany): array
    {
        return $this->nonComplianceSummaries[$studentCompany->id] ??= app(NonComplianceReportService::class)->summary($studentCompany);
    }

    public function render()
    {
        return view('ppuds::livewire.pages.non-compliance-report.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Reports'), 'url' => route('reports.index')],
                ['title' => __('Non Compliance Report'), 'url' => route('non-compliance-reports.index')],
            ],
        ]);
    }
}

<?php

namespace Modules\PPUDS\Livewire\Pages\AbsenceReport;

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
use Modules\PPUDS\Services\AbsenceReportService;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\HasSupervisorFilter;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HasSupervisorFilter;
    use ScopesStudentCompanyVisibility;

    private ?array $absentStudentCompanyIds = null;

    private array $absenceSummaries = [];

    public function table(Table $table): Table
    {
        $studentCompanyTable = (new StudentCompany)->getTable();

        return $table
            ->query(fn () => StudentCompany::query()
                ->with([
                    'attendances',
                    'branch.workingHours',
                    'company',
                    'leaveRequests',
                    'registration',
                    'student.studentProfile',
                ])
                ->whereIn("{$studentCompanyTable}.id", $this->absentStudentCompanyIds()))
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

                TextColumn::make('required_working_days')
                    ->label(__('Required Working Days'))
                    ->getStateUsing(fn (StudentCompany $record) => $this->summaryValue($record, 'required_working_days'))
                    ->wrapHeader(),

                TextColumn::make('attendance_days')
                    ->label(__('Attendance Days'))
                    ->getStateUsing(fn (StudentCompany $record) => $this->summaryValue($record, 'attendance_days')),

                TextColumn::make('total_absence_days')
                    ->label(__('Total Absence Days'))
                    ->getStateUsing(fn (StudentCompany $record) => $this->summaryValue($record, 'total_absence_days'))
                    ->badge()
                    ->color('danger')
                    ->wrapHeader(),

                TextColumn::make('excused_absence_days')
                    ->label(__('Excused Absence Days'))
                    ->getStateUsing(fn (StudentCompany $record) => $this->summaryValue($record, 'excused_absence_days'))
                    ->badge()
                    ->color('success')
                    ->wrapHeader(),

                TextColumn::make('unexcused_absence_days')
                    ->label(__('Unexcused Absence Days'))
                    ->getStateUsing(fn (StudentCompany $record) => $this->summaryValue($record, 'unexcused_absence_days'))
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'danger' : 'gray')
                    ->wrapHeader(),

                TextColumn::make('actual_absence_days')
                    ->label(__('Actual Absence Days'))
                    ->getStateUsing(fn (StudentCompany $record) => $this->summaryValue($record, 'actual_absence_days'))
                    ->wrapHeader(),

                TextColumn::make('leave_request_days')
                    ->label(__('Leave Request Days'))
                    ->getStateUsing(fn (StudentCompany $record) => $this->summaryValue($record, 'leave_request_days'))
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
                ->query(function (Builder $query, array $data) {
                    if (! empty($data['number'])) {
                        $query->where(function (Builder $query) use ($data) {
                            $query->whereHas(
                                'student.studentProfile',
                                fn (Builder $studentProfileQuery) => $studentProfileQuery->where('student_number', 'like', "%{$data['number']}%")
                            )->orWhereHas(
                                'student',
                                fn (Builder $studentQuery) => $studentQuery->where('name', 'like', "%{$data['number']}%")
                            );
                        });
                    }
                }),

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => $this->applyCompanyVisibilityScope(Company::query())
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload(),

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

    private function absentStudentCompanyIds(): array
    {
        if ($this->absentStudentCompanyIds !== null) {
            return $this->absentStudentCompanyIds;
        }

        $settings = app(GeneralSettings::class);

        $this->absentStudentCompanyIds = StudentCompany::query()
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
            ->filter(fn (StudentCompany $studentCompany) => $this->summaryValue($studentCompany, 'total_absence_days') > 0)
            ->pluck('id')
            ->values()
            ->all();

        return $this->absentStudentCompanyIds;
    }

    private function summaryValue(StudentCompany $studentCompany, string $key): int|string|null
    {
        return $this->summary($studentCompany)[$key] ?? 0;
    }

    private function summary(StudentCompany $studentCompany): array
    {
        return $this->absenceSummaries[$studentCompany->id] ??= app(AbsenceReportService::class)->summary($studentCompany);
    }

    public function render()
    {
        return view('ppuds::livewire.pages.absence-report.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Reports'), 'url' => route('reports.index')],
                ['title' => __('Absence Report'), 'url' => route('absence-reports.index')],
            ],
        ]);
    }
}

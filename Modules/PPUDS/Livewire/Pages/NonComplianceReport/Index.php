<?php

namespace Modules\PPUDS\Livewire\Pages\NonComplianceReport;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Services\NonComplianceReportService;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\HasSupervisorFilter;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Index extends Component implements HasForms
{
    use InteractsWithForms;
    use HasSupervisorFilter;
    use ScopesStudentCompanyVisibility;
    use WithPagination;

    public ?array $filters = [];

    private array $nonComplianceSummaries = [];

    public function mount(): void
    {
        $settings = app(GeneralSettings::class);

        $this->form->fill([
            'search' => null,
            'company_id' => null,
            'supervisor_id' => null,
            'non_compliance_types' => [],
            'minimum_late_hours' => null,
            'outside_work_range_distance_meters' => 200,
            'date' => now()->toDateString(),
            'date_from' => null,
            'date_to' => null,
            'year' => $settings->year,
            'semester_type' => $settings->semester_type->value,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Search Details'))
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2, 'xl' => 3, '2xl' => 6])
                            ->schema([
                                TextInput::make('search')
                                    ->label(__('Number / Name'))
                                    ->live(debounce: 500),

                                Select::make('company_id')
                                    ->label(__('Company'))
                                    ->options(fn (): array => $this->companyOptions())
                                    ->searchable()
                                    ->preload()
                                    ->live(),

                                Select::make('supervisor_id')
                                    ->label(__('Supervisor'))
                                    ->options(fn (): array => $this->supervisorFilterOptions())
                                    ->searchable()
                                    ->preload()
                                    ->live(),

                                Select::make('non_compliance_types')
                                    ->label(__('Non Compliance Type'))
                                    ->options($this->nonComplianceTypeOptions())
                                    ->multiple()
                                    ->native(false)
                                    ->live(),

                                TextInput::make('minimum_late_hours')
                                    ->label(__('Minimum Late Hours'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.25')
                                    ->live(debounce: 500),

                                TextInput::make('outside_work_range_distance_meters')
                                    ->label(__('Allowed Range (meters)'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(200)
                                    ->live(debounce: 500),

                                DatePicker::make('date')
                                    ->label(__('Specific Day'))
                                    ->native(false)
                                    ->closeOnDateSelection()
                                    ->live(),

                                DatePicker::make('date_from')
                                    ->label(__('From Date'))
                                    ->native(false)
                                    ->closeOnDateSelection()
                                    ->live(),

                                DatePicker::make('date_to')
                                    ->label(__('To Date'))
                                    ->native(false)
                                    ->closeOnDateSelection()
                                    ->live(),

                                TextInput::make('year')
                                    ->label(__('Academic Year'))
                                    ->numeric()
                                    ->placeholder(date('Y'))
                                    ->live(debounce: 500),

                                Select::make('semester_type')
                                    ->label(__('Semester Type'))
                                    ->options(SemesterType::options())
                                    ->live(),
                            ]),
                    ]),
            ])
            ->statePath('filters');
    }

    public function updated($property, $value = null): void
    {
        if (is_string($property) && str_starts_with($property, 'filters.')) {
            $this->resetPage();
            $this->nonComplianceSummaries = [];
        }
    }

    public function records(): LengthAwarePaginator
    {
        $query = $this->filteredBaseQuery();
        $nonCompliantIds = app(NonComplianceReportService::class)
            ->nonCompliantStudentCompanyIds(
                clone $query,
                ...[
                    ...$this->dateFilters(),
                    $this->selectedNonComplianceTypes(),
                    $this->outsideWorkRangeDistanceMeters(),
                ]
            );

        return $query
            ->whereKey($nonCompliantIds)
            ->orderBy('id')
            ->paginate(9);
    }

    public function summary(StudentCompany $studentCompany): array
    {
        return $this->nonComplianceSummaries[$studentCompany->id]
            ??= app(NonComplianceReportService::class)->summary(
                $studentCompany,
                ...[
                    ...$this->dateFilters(),
                    $this->outsideWorkRangeDistanceMeters(),
                ]
            );
    }

    private function filteredBaseQuery(): Builder
    {
        $query = StudentCompany::query()
            ->with([
                'attendances',
                'branch.workingHours',
                'company',
                'leaveRequests',
                'registration',
                'student.studentProfile',
            ])
            ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query));

        $search = trim((string) ($this->filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereHas(
                    'student.studentProfile',
                    fn (Builder $studentProfileQuery) => $studentProfileQuery->where('student_number', 'like', "%{$search}%")
                )->orWhereHas(
                    'student',
                    fn (Builder $studentQuery) => $studentQuery->where('name', 'like', "%{$search}%")
                );
            });
        }

        $query
            ->when(
                filled($this->filters['company_id'] ?? null),
                fn (Builder $query): Builder => $query->where('company_id', (int) $this->filters['company_id'])
            )
            ->when(
                filled($this->filters['supervisor_id'] ?? null),
                fn (Builder $query): Builder => $query->whereHas(
                    'registration',
                    fn (Builder $registrationQuery): Builder => $registrationQuery->where('supervisor_id', (int) $this->filters['supervisor_id'])
                )
            )
            ->when(
                filled($this->filters['year'] ?? null),
                fn (Builder $query): Builder => $query->whereHas(
                    'registration',
                    fn (Builder $registrationQuery): Builder => $registrationQuery->where('year', $this->filters['year'])
                )
            )
            ->when(
                filled($this->filters['semester_type'] ?? null),
                fn (Builder $query): Builder => $query->whereHas(
                    'registration',
                    fn (Builder $registrationQuery): Builder => $registrationQuery->where('semester', $this->filters['semester_type'])
                )
            );

        if (is_numeric($this->filters['minimum_late_hours'] ?? null)) {
            app(NonComplianceReportService::class)
                ->applyMinimumLateHoursFilter($query, $this->filters['minimum_late_hours'], ...$this->dateFilters());
        }

        return $query;
    }

    private function dateFilters(): array
    {
        return [
            $this->filledFilter('date'),
            $this->filledFilter('date_from'),
            $this->filledFilter('date_to'),
        ];
    }

    private function filledFilter(string $key): ?string
    {
        $value = $this->filters[$key] ?? null;

        return filled($value) ? (string) $value : null;
    }

    private function companyOptions(): array
    {
        return $this->applyCompanyVisibilityScope(Company::query())
            ->get()
            ->pluck('name', 'id')
            ->sort()
            ->toArray();
    }

    private function nonComplianceTypeOptions(): array
    {
        return [
            NonComplianceReportService::ISSUE_OUTSIDE_WORK_RANGE => __('Outside Work Range'),
            NonComplianceReportService::ISSUE_LATE_ATTENDANCE => __('Late Attendance'),
            NonComplianceReportService::ISSUE_ABSENCE => __('Non Attendance'),
        ];
    }

    private function selectedNonComplianceTypes(): array
    {
        $types = $this->filters['non_compliance_types'] ?? [];

        return collect(is_array($types) ? $types : [$types])
            ->filter()
            ->values()
            ->all();
    }

    private function outsideWorkRangeDistanceMeters(): int
    {
        $distance = (int) ($this->filters['outside_work_range_distance_meters'] ?? 200);

        return $distance > 0 ? $distance : 200;
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

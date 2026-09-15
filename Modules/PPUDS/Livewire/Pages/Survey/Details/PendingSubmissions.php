<?php

namespace Modules\PPUDS\Livewire\Pages\Survey\Details;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action as TablesAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;
use Maatwebsite\Excel\Excel as WriterType;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Enums\StudentGender;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Exports\SurveyPendingSubmissionsExport;
use Modules\PPUDS\Support\HandlesCompanySupervisorSurveyEvaluations;
use Modules\PPUDS\Support\HasSupervisorFilter;

class PendingSubmissions extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HandlesCompanySupervisorSurveyEvaluations;
    use HasSupervisorFilter;

    public int $surveyId;

    public ?Survey $survey = null;

    public function mount(int $surveyId)
    {
        $this->surveyId = $surveyId;
        $this->survey = Survey::findOrFail($surveyId);
    }

    public function table(Table $table): Table
    {
        if ($this->survey && $this->isCompanySupervisorSurvey($this->survey)) {
            return $this->companyPendingEvaluationTable($table);
        }

        return $table
            ->query(fn () => User::query()
                ->with(['roles', 'studentProfile.major'])
                ->when(
                    $this->survey?->serve_group,
                    fn (Builder $query, string $role) => $query->role($role),
                    fn (Builder $query) => $query->whereRaw('1 = 0')
                )
                ->when(
                    $this->survey?->major_id,
                    fn (Builder $query, int $majorId) => $query->whereHas('studentProfile', fn (Builder $profileQuery) => $profileQuery->where('major_id', $majorId))
                )
                ->whereNotIn('users.id', SurveyAnswer::query()
                    ->select('submitted_by')
                    ->where('survey_id', $this->surveyId)
                    ->distinct()
                )
            )
            ->columns([
                UserColumn::make('name')
                    ->label(__('Name'))
                    ->user(fn (User $record) => $record)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('studentProfile.student_number')
                    ->label(__('Student Number'))
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('studentProfile.major.name')
                    ->label(__('Major'))
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('target_group')
                    ->label(__('Target Group'))
                    ->state(fn (): string => $this->formatTargetGroup())
                    ->badge()
                    ->color('primary'),

                TextColumn::make('submission_status')
                    ->label(__('Status'))
                    ->state(fn (): string => __('Not Submitted'))
                    ->badge()
                    ->color('warning'),
            ])
            ->filters($this->getUserTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->headerActions($this->getExportHeaderActions())
            ->emptyStateHeading(__('No pending submissions found'));
    }

    protected function companyPendingEvaluationTable(Table $table): Table
    {
        $studentCompaniesTable = (new StudentCompany)->getTable();

        return $table
            ->query(fn () => $this->currentSurveyStudentCompaniesQuery($this->survey)
                ->select("{$studentCompaniesTable}.*")
                ->whereNotIn("{$studentCompaniesTable}.id", SurveyAnswer::query()
                    ->select('student_company_id')
                    ->where('survey_id', $this->surveyId)
                    ->whereNotNull('student_company_id')
                    ->distinct()
                )
            )
            ->columns([
                UserColumn::make('student.name')
                    ->label(__('Evaluated Student'))
                    ->user(fn (StudentCompany $record) => $record->student)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('student.email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('student.studentProfile.student_number')
                    ->label(__('Student Number'))
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('student.studentProfile.major.name')
                    ->label(__('Major'))
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('department.name')
                    ->label(__('Department'))
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('submission_status')
                    ->label(__('Status'))
                    ->state(fn (): string => __('Not Submitted'))
                    ->badge()
                    ->color('warning'),
            ])
            ->filters($this->getStudentCompanyTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->headerActions($this->getExportHeaderActions())
            ->emptyStateHeading(__('No pending submissions found'));
    }

    /**
     * فلاتر جدول "بانتظار التسليم" حين تكون الفئة المستهدفة مستخدمين مباشرة.
     * فلاتر ملف الطالب تظهر فقط للاستبيانات الموجّهة للطلبة لأنها بلا معنى لغيرهم.
     */
    protected function getUserTableFilters(): array
    {
        $isStudentSurvey = $this->survey?->serve_group === UserRole::STUDENT->value;

        return [
            Filter::make('name')
                ->label(__('Name'))
                ->form([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->prefixIcon('solar-user-id-bold-duotone')
                        ->live(debounce: 500),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['name'] ?? null,
                    fn (Builder $query, string $name): Builder => $query->where('users.name', 'like', "%{$name}%")
                )),

            Filter::make('email')
                ->label(__('Email'))
                ->form([
                    TextInput::make('email')
                        ->label(__('Email'))
                        ->live(debounce: 500),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['email'] ?? null,
                    fn (Builder $query, string $email): Builder => $query->where('users.email', 'like', "%{$email}%")
                )),

            Filter::make('phone')
                ->label(__('Phone'))
                ->form([
                    TextInput::make('phone')
                        ->label(__('Phone'))
                        ->live(debounce: 500),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['phone'] ?? null,
                    fn (Builder $query, string $phone): Builder => $query->where('users.phone', 'like', "%{$phone}%")
                )),

            Filter::make('student_number')
                ->label(__('Student Number'))
                ->form([
                    TextInput::make('student_number')
                        ->label(__('Student Number'))
                        ->prefixIcon('solar-user-id-bold-duotone')
                        ->live(debounce: 500),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['student_number'] ?? null,
                    fn (Builder $query, string $studentNumber): Builder => $query->whereHas(
                        'studentProfile',
                        fn (Builder $profileQuery): Builder => $profileQuery->where('student_number', 'like', "%{$studentNumber}%")
                    )
                ))
                ->visible($isStudentSurvey),

            SelectFilter::make('major_id')
                ->label(__('Major'))
                ->options(fn (): array => Major::with('translations')->get()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->native(false)
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['value'] ?? null),
                    fn (Builder $query): Builder => $query->whereHas(
                        'studentProfile',
                        fn (Builder $profileQuery): Builder => $profileQuery->where('major_id', (int) $data['value'])
                    )
                )),

            SelectFilter::make('gender')
                ->label(__('Gender'))
                ->options(StudentGender::options())
                ->native(false)
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['value'] ?? null),
                    fn (Builder $query): Builder => $query->whereHas(
                        'studentProfile',
                        fn (Builder $profileQuery): Builder => $profileQuery->where('gender', (int) $data['value'])
                    )
                ))
                ->visible($isStudentSurvey),

            Filter::make('enrollment_year')
                ->label(__('Enrollment Year'))
                ->form([
                    TextInput::make('enrollment_year')
                        ->label(__('Enrollment Year'))
                        ->prefixIcon('solar-calendar-search-bold-duotone')
                        ->numeric()
                        ->live(debounce: 500),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['enrollment_year'] ?? null,
                    fn (Builder $query, $enrollmentYear): Builder => $query->whereHas(
                        'studentProfile',
                        fn (Builder $profileQuery): Builder => $profileQuery->where('enrollment_year', $enrollmentYear)
                    )
                ))
                ->visible($isStudentSurvey),

            SelectFilter::make('semester_level')
                ->label(__('Semester Level'))
                ->options(array_combine(range(1, 10), range(1, 10)))
                ->native(false)
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['value'] ?? null),
                    fn (Builder $query): Builder => $query->whereHas(
                        'studentProfile',
                        fn (Builder $profileQuery): Builder => $profileQuery->where('semester_level', (int) $data['value'])
                    )
                ))
                ->visible($isStudentSurvey),

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => Company::query()->get()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->native(false)
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['value'] ?? null),
                    fn (Builder $query): Builder => $query->whereHas(
                        'studentCompanies',
                        fn (Builder $studentCompanyQuery): Builder => $studentCompanyQuery->where('company_id', (int) $data['value'])
                    )
                ))
                ->visible($isStudentSurvey),

            TernaryFilter::make('has_company')
                ->label(__('Has Company'))
                ->placeholder(__('All'))
                ->native(false)
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereHas('studentCompanies', fn (Builder $studentCompanyQuery): Builder => $studentCompanyQuery->whereNotNull('company_id')),
                    false: fn (Builder $query): Builder => $query->whereDoesntHave('studentCompanies', fn (Builder $studentCompanyQuery): Builder => $studentCompanyQuery->whereNotNull('company_id')),
                    blank: fn (Builder $query): Builder => $query,
                )
                ->visible($isStudentSurvey),

            $this->supervisorSelectFilter('studentCompanies.registration')
                ->native(false)
                ->visible($isStudentSurvey),

            Filter::make('created_at')
                ->label(__('Created At'))
                ->form([
                    DatePicker::make('created_from')
                        ->label(__('From Date'))
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->live(),

                    DatePicker::make('created_to')
                        ->label(__('To Date'))
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->afterOrEqual('created_from')
                        ->live(),
                ])
                ->columns(2)
                ->columnSpan(2)
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(
                        $data['created_from'] ?? null,
                        fn (Builder $query, $date): Builder => $query->whereDate('users.created_at', '>=', $date)
                    )
                    ->when(
                        $data['created_to'] ?? null,
                        fn (Builder $query, $date): Builder => $query->whereDate('users.created_at', '<=', $date)
                    ))
                ->indicateUsing(fn (array $data): array => $this->dateRangeIndicators($data, 'created_from', 'created_to')),
        ];
    }

    /**
     * فلاتر جدول "بانتظار التسليم" حين يقيّم مشرفو الشركات طلبتهم، فالسجل هنا
     * تدريب طالب في شركة لا مستخدماً.
     */
    protected function getStudentCompanyTableFilters(): array
    {
        return [
            Filter::make('student_name')
                ->label(__('Evaluated Student'))
                ->form([
                    TextInput::make('student_name')
                        ->label(__('Evaluated Student'))
                        ->prefixIcon('solar-user-id-bold-duotone')
                        ->live(debounce: 500),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['student_name'] ?? null,
                    fn (Builder $query, string $name): Builder => $query->whereHas(
                        'student',
                        fn (Builder $studentQuery): Builder => $studentQuery->where('name', 'like', "%{$name}%")
                    )
                )),

            Filter::make('student_email')
                ->label(__('Email'))
                ->form([
                    TextInput::make('student_email')
                        ->label(__('Email'))
                        ->live(debounce: 500),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['student_email'] ?? null,
                    fn (Builder $query, string $email): Builder => $query->whereHas(
                        'student',
                        fn (Builder $studentQuery): Builder => $studentQuery->where('email', 'like', "%{$email}%")
                    )
                )),

            Filter::make('student_number')
                ->label(__('Student Number'))
                ->form([
                    TextInput::make('student_number')
                        ->label(__('Student Number'))
                        ->prefixIcon('solar-user-id-bold-duotone')
                        ->live(debounce: 500),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['student_number'] ?? null,
                    fn (Builder $query, string $studentNumber): Builder => $query->whereHas(
                        'student.studentProfile',
                        fn (Builder $profileQuery): Builder => $profileQuery->where('student_number', 'like', "%{$studentNumber}%")
                    )
                )),

            SelectFilter::make('major_id')
                ->label(__('Major'))
                ->options(fn (): array => Major::with('translations')->get()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->native(false)
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['value'] ?? null),
                    fn (Builder $query): Builder => $query->whereHas(
                        'student.studentProfile',
                        fn (Builder $profileQuery): Builder => $profileQuery->where('major_id', (int) $data['value'])
                    )
                )),

            SelectFilter::make('gender')
                ->label(__('Gender'))
                ->options(StudentGender::options())
                ->native(false)
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['value'] ?? null),
                    fn (Builder $query): Builder => $query->whereHas(
                        'student.studentProfile',
                        fn (Builder $profileQuery): Builder => $profileQuery->where('gender', (int) $data['value'])
                    )
                )),

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => Company::query()->get()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->native(false),

            SelectFilter::make('branch_id')
                ->label(__('Branch'))
                ->options(fn (): array => Branch::query()->get()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->native(false),

            SelectFilter::make('department_id')
                ->label(__('Department'))
                ->options(fn (): array => CompanyDepartment::query()->get()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->native(false),

            SelectFilter::make('status')
                ->label(__('Training Status'))
                ->options(TrainingStatus::options())
                ->native(false),

            SelectFilter::make('course_id')
                ->label(__('Course'))
                ->options(fn (): array => Course::query()->get()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->native(false)
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['value'] ?? null),
                    fn (Builder $query): Builder => $query->whereHas(
                        'registration',
                        fn (Builder $registrationQuery): Builder => $registrationQuery->where('course_id', (int) $data['value'])
                    )
                )),

            $this->supervisorSelectFilter('registration')
                ->native(false),

            SelectFilter::make('evaluation_supervisor_id')
                ->label(__('Evaluation Supervisor'))
                ->options(fn (): array => User::role(UserRole::EVALUATION_SUPERVISOR->value)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload()
                ->native(false),

            Filter::make('created_at')
                ->label(__('Created At'))
                ->form([
                    DatePicker::make('created_from')
                        ->label(__('From Date'))
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->live(),

                    DatePicker::make('created_to')
                        ->label(__('To Date'))
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->afterOrEqual('created_from')
                        ->live(),
                ])
                ->columns(2)
                ->columnSpan(2)
                ->query(function (Builder $query, array $data): Builder {
                    $studentCompaniesTable = (new StudentCompany)->getTable();

                    return $query
                        ->when(
                            $data['created_from'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereDate("{$studentCompaniesTable}.created_at", '>=', $date)
                        )
                        ->when(
                            $data['created_to'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereDate("{$studentCompaniesTable}.created_at", '<=', $date)
                        );
                })
                ->indicateUsing(fn (array $data): array => $this->dateRangeIndicators($data, 'created_from', 'created_to')),
        ];
    }

    protected function dateRangeIndicators(array $data, string $fromField, string $toField): array
    {
        $indicators = [];

        if (! empty($data[$fromField])) {
            $indicators[] = Indicator::make(__('From Date').': '.Carbon::parse($data[$fromField])->toDateString())
                ->removeField($fromField);
        }

        if (! empty($data[$toField])) {
            $indicators[] = Indicator::make(__('To Date').': '.Carbon::parse($data[$toField])->toDateString())
                ->removeField($toField);
        }

        return $indicators;
    }

    /**
     * التصدير يعتمد استعلام الجدول بعد الفلاتر والبحث، فيخرج الملف مطابقاً
     * لما يراه المستخدم على الشاشة لا لكل المتأخرين.
     */
    protected function getExportHeaderActions(): array
    {
        return [
            TablesAction::make('export_pending_submissions')
                ->label(__('Export Pending Submissions'))
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->action(fn () => app(ExcelServiceInterface::class)->download(
                    new SurveyPendingSubmissionsExport($this->survey, $this->getTableQueryForExport()),
                    $this->exportFilename(),
                    WriterType::XLSX
                ))
                ->visible(fn (): bool => $this->survey !== null && $this->canViewSurveyDetails()),
        ];
    }

    protected function exportFilename(): string
    {
        $slug = Str::slug((string) $this->survey?->title);

        return 'survey-pending-submissions-'.($slug ?: $this->surveyId).'-'.now()->format('Y-m-d-His').'.xlsx';
    }

    protected function canViewSurveyDetails(): bool
    {
        return auth()->user()?->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::PRACTICAL_TRAINING_SUPERVISOR->value,
            'Academic Supervisor',
            'University Supervisor',
        ]) ?? false;
    }

    protected function formatTargetGroup(): string
    {
        $role = $this->survey?->serve_group;

        return $role
            ? UserRole::tryFrom($role)?->getLabel() ?? $role
            : '-';
    }

    public function render()
    {
        return view('ppuds::livewire.pages.survey.details.pending-submissions')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Surveys'), 'url' => route('surveys.index')],
            ],
        ]);
    }
}

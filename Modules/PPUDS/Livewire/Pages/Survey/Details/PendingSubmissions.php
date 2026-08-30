<?php

namespace Modules\PPUDS\Livewire\Pages\Survey\Details;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Support\HandlesCompanySupervisorSurveyEvaluations;

class PendingSubmissions extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HandlesCompanySupervisorSurveyEvaluations;

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
            ->emptyStateHeading(__('No pending submissions found'));
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

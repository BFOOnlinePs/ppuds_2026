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
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;

class Submissions extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public int $surveyId;

    public ?Survey $survey = null;

    public function mount(int $surveyId)
    {
        $this->surveyId = $surveyId;
        $this->survey = Survey::findOrFail($surveyId);
    }

    public function table(Table $table): Table
    {
        $answersTable = (new SurveyAnswer)->getTable();

        return $table
            ->query(fn () => User::query()
                ->with(['roles', 'studentProfile.major'])
                ->select('users.*')
                ->selectSub(
                    SurveyAnswer::query()
                        ->selectRaw('COUNT(*)')
                        ->where('survey_id', $this->surveyId)
                        ->whereColumn("{$answersTable}.submitted_by", 'users.id'),
                    'survey_answers_count'
                )
                ->selectSub(
                    SurveyAnswer::query()
                        ->selectRaw('MAX(created_at)')
                        ->where('survey_id', $this->surveyId)
                        ->whereColumn("{$answersTable}.submitted_by", 'users.id'),
                    'survey_submitted_at'
                )
                ->when($this->survey?->serve_group, fn (Builder $query, string $role) => $query->role($role))
                ->when(
                    $this->survey?->major_id,
                    fn (Builder $query, int $majorId) => $query->whereHas('studentProfile', fn (Builder $profileQuery) => $profileQuery->where('major_id', $majorId))
                )
                ->whereIn('users.id', SurveyAnswer::query()
                    ->select('submitted_by')
                    ->where('survey_id', $this->surveyId)
                    ->distinct()
                )
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->url(fn (User $record): string => route('surveys.submission-details', [
                        'survey' => $this->surveyId,
                        'user' => $record->id,
                    ]))
                    ->color('primary')
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

                TextColumn::make('survey_answers_count')
                    ->label(__('Answers Count'))
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('survey_submitted_at')
                    ->label(__('Submitted At'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->emptyStateHeading(__('No submissions found'));
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
        return view('ppuds::livewire.pages.survey.details.submissions')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Surveys'), 'url' => route('surveys.index')],
            ],
        ]);
    }
}

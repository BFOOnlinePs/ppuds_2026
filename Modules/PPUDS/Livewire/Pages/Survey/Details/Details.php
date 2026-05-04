<?php

namespace Modules\PPUDS\Livewire\Pages\Survey\Details;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;

class Details extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public Survey $survey;

    public int $targetUsersCount = 0;

    public int $submittedUsersCount = 0;

    public int $pendingUsersCount = 0;

    public function mount(Survey $survey)
    {
        abort_unless($this->canViewSurveyDetails(), 403);

        $this->survey = $survey->load([
            'questions' => fn ($query) => $query->orderBy('sort_order'),
            'questions.options' => fn ($query) => $query->orderBy('sort_order'),
        ]);

        $this->loadSubmissionStats();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->survey)
            ->schema([
                Tabs::make('Survey Details Tabs')
                    ->tabs([
                        Tabs\Tab::make(__('Overview'))
                            ->icon('solar-info-circle-bold-duotone')
                            ->schema([
                                Section::make(__('Survey Details'))
                                    ->schema([
                                        Grid::make(['default' => 1, 'md' => 3])
                                            ->schema([
                                                TextEntry::make('title')
                                                    ->label(__('Survey Title'))
                                                    ->weight('bold')
                                                    ->columnSpan(['default' => 1, 'md' => 2]),

                                                TextEntry::make('is_active')
                                                    ->label(__('Active'))
                                                    ->badge()
                                                    ->formatStateUsing(fn (bool $state): string => $state ? __('Active') : __('Inactive'))
                                                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),

                                                TextEntry::make('serve_group')
                                                    ->label(__('Target Group'))
                                                    ->badge()
                                                    ->formatStateUsing(fn (?string $state): string => $this->formatTargetGroup($state)),

                                                TextEntry::make('semester')
                                                    ->label(__('Semester'))
                                                    ->badge()
                                                    ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? '-'),

                                                TextEntry::make('year')
                                                    ->label(__('Year'))
                                                    ->placeholder('-'),

                                                TextEntry::make('start_date')
                                                    ->label(__('Start Date'))
                                                    ->date('Y-m-d')
                                                    ->placeholder('-'),

                                                TextEntry::make('end_date')
                                                    ->label(__('End Date'))
                                                    ->date('Y-m-d')
                                                    ->placeholder('-'),

                                                TextEntry::make('questions_count')
                                                    ->label(__('Questions Count'))
                                                    ->state(fn (): int => $this->survey->questions->count())
                                                    ->badge()
                                                    ->color('info'),

                                                TextEntry::make('submitted_users_count')
                                                    ->label(__('Submitted Count'))
                                                    ->state(fn (): int => $this->submittedUsersCount)
                                                    ->badge()
                                                    ->color('success'),

                                                TextEntry::make('description')
                                                    ->label(__('Description'))
                                                    ->prose()
                                                    ->columnSpanFull()
                                                    ->placeholder('-'),
                                            ]),
                                    ]),

                                Section::make(__('Target Groups'))
                                    ->schema([
                                        RepeatableEntry::make('target_groups')
                                            ->hiddenLabel()
                                            ->state(fn (): array => $this->targetGroupsSummary())
                                            ->schema([
                                                TextEntry::make('name')
                                                    ->label(__('Target Group'))
                                                    ->badge()
                                                    ->color('primary'),

                                                TextEntry::make('total_users')
                                                    ->label(__('Total Required Submissions'))
                                                    ->badge()
                                                    ->color('info'),

                                                TextEntry::make('pending_users')
                                                    ->label(__('Pending Submissions Count'))
                                                    ->badge()
                                                    ->color('warning'),
                                            ])
                                            ->columns(['default' => 1, 'md' => 3]),
                                    ]),

                            ]),

                        Tabs\Tab::make(__('Submissions'))
                            ->icon('solar-check-circle-bold-duotone')
                            ->badge($this->submittedUsersCount)
                            ->schema([
                                Livewire::make(Submissions::class, [
                                    'surveyId' => $this->survey->id,
                                ])->columnSpanFull(),
                            ]),

                        Tabs\Tab::make(__('Pending Submission'))
                            ->icon('solar-clock-circle-bold-duotone')
                            ->badge($this->pendingUsersCount)
                            ->schema([
                                Livewire::make(PendingSubmissions::class, [
                                    'surveyId' => $this->survey->id,
                                ])->columnSpanFull(),
                            ]),

                        Tabs\Tab::make(__('Statistics'))
                            ->icon('solar-chart-2-bold-duotone')
                            ->schema([]),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    protected function loadSubmissionStats(): void
    {
        $this->targetUsersCount = $this->survey->serve_group
            ? $this->targetUsersQuery()->count()
            : 0;

        $this->submittedUsersCount = SurveyAnswer::query()
            ->where('survey_id', $this->survey->id)
            ->when(
                $this->survey->serve_group,
                fn (Builder $query) => $query->whereHas('submittedBy', fn (Builder $userQuery) => $userQuery->role($this->survey->serve_group))
            )
            ->distinct('submitted_by')
            ->count('submitted_by');

        $this->pendingUsersCount = max($this->targetUsersCount - $this->submittedUsersCount, 0);
    }

    protected function targetUsersQuery(): Builder
    {
        return User::query()
            ->when(
                $this->survey->serve_group,
                fn (Builder $query, string $role) => $query->role($role),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            );
    }

    protected function targetGroupsSummary(): array
    {
        if (! $this->survey->serve_group) {
            return [];
        }

        return [
            [
                'name' => $this->formatTargetGroup($this->survey->serve_group),
                'total_users' => $this->targetUsersCount,
                'pending_users' => $this->pendingUsersCount,
            ],
        ];
    }

    protected function formatTargetGroup(?string $role): string
    {
        return $role
            ? UserRole::tryFrom($role)?->getLabel() ?? $role
            : '-';
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

    public function render()
    {
        return view('ppuds::livewire.pages.survey.details.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Surveys'), 'url' => route('surveys.index')],
                ['title' => __('Survey Details'), 'url' => route('surveys.details', $this->survey)],
            ],
        ]);
    }
}

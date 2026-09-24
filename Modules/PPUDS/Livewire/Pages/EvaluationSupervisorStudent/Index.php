<?php

namespace Modules\PPUDS\Livewire\Pages\EvaluationSupervisorStudent;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
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
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Settings\GeneralSettings;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->evaluationAssignedQuery()
                ->with([
                    'student.studentProfile.major',
                    'evaluationSupervisor',
                    'company',
                    'department',
                    'registration.finalReport',
                    'registration.media',
                ])
                ->withAttendanceDays()
                ->withActualWorkingHours())
            ->columns([
                TextColumn::make('student.studentProfile.student_number')
                    ->label(__('Student Number'))
                    ->searchable()
                    ->placeholder('---'),

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

                TextColumn::make('department.name')
                    ->label(__('Department'))
                    ->placeholder('---')
                    ->toggleable(isToggledHiddenByDefault: true),

                UserColumn::make('evaluationSupervisor.name')
                    ->label(__('Evaluation Supervisor'))
                    ->user(fn (StudentCompany $record) => $record->evaluationSupervisor)
                    ->linksToEvaluationSupervisor()
                    ->toggleable()
                    ->visible(fn (): bool => ! $this->shouldScopeToAuthenticatedSupervisor()),

                TextColumn::make('attendance_days')
                    ->label(__('Attendance Days'))
                    ->badge()
                    ->color('info'),

                TextColumn::make('actual_working_hours')
                    ->label(__('Attendance Hours'))
                    ->badge()
                    ->color('info'),

                TextColumn::make('evaluation_score')
                    ->label(fn (): string => __('Grade (out of :max)', ['max' => $this->maxGrade()]))
                    ->badge()
                    ->color(fn (?int $state): string => $state === null ? 'gray' : 'success')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? __('Not graded yet')
                        : $state.' / '.$this->maxGrade()),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
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
        ];
    }

    protected function getTableActions(): array
    {
        return [
            // السجل يُفتح على الطالب لا على التدريب، ليظهر مساره في كل الشركات معاً.
            Action::make('details')
                ->label(__('Student Training Details'))
                ->icon('solar-eye-bold-duotone')
                ->color('gray')
                ->size('xl')
                ->url(fn (StudentCompany $record): ?string => $record->student_id
                    ? route('evaluation-supervisor-students.details', $record->student_id)
                    : null)
                ->visible(fn (StudentCompany $record): bool => $record->student_id !== null
                    && auth()->user()->can('EvaluationSupervisorStudent Details')),

            // التقرير النهائي كاملاً في تبويبه داخل سجل الطالب.
            Action::make('final_report')
                ->label(__('Final Report'))
                ->icon('solar-document-text-bold-duotone')
                ->color('info')
                ->url(fn (StudentCompany $record): ?string => $record->student_id
                    ? route('evaluation-supervisor-students.details', ['user' => $record->student_id, 'tab' => 'final-report'])
                    : null)
                ->visible(fn (StudentCompany $record): bool => $record->student_id !== null
                    && $record->registration?->finalReport !== null
                    && auth()->user()->can('EvaluationSupervisorStudent Details')),

            Action::make('view_final_presentation')
                ->label(__('Presentation File'))
                ->icon('solar-projector-bold-duotone')
                ->color('primary')
                ->url(fn (StudentCompany $record): ?string => $record->registration?->getFirstMediaUrl(Registration::PRESENTATION_COLLECTION) ?: null)
                ->openUrlInNewTab()
                ->visible(fn (StudentCompany $record): bool => auth()->user()->can('EvaluationSupervisorStudent Details')
                    && (bool) $record->registration?->hasMedia(Registration::PRESENTATION_COLLECTION)),

            Action::make('view_final_file')
                ->label(__('View File'))
                ->icon('solar-paperclip-2-bold-duotone')
                ->color('gray')
                ->url(fn (StudentCompany $record): ?string => $record->registration?->getFirstMediaUrl('final_file') ?: null)
                ->openUrlInNewTab()
                ->visible(fn (StudentCompany $record): bool => auth()->user()->can('EvaluationSupervisorStudent Details')
                    && (bool) $record->registration?->hasMedia('final_file')),

            Action::make('grade')
                ->label(fn (StudentCompany $record): string => $record->evaluation_score === null ? __('Set Grade') : __('Grade'))
                ->icon('heroicon-o-star')
                ->color('primary')
                ->form(fn (StudentCompany $record): array => [
                    // مجموع دوام الطالب في كل تدريباته، بنفس حساب سجل الطالب.
                    Section::make(__('Attendance across all companies'))
                        ->columns(2)
                        ->schema([
                            Placeholder::make('attendance_days')
                                ->label(__('Attendance Days'))
                                ->content(fn (): int => $this->studentAttendanceTotals($record)['days']),

                            Placeholder::make('actual_working_hours')
                                ->label(__('Actual Working Hours'))
                                ->content(fn (): float => $this->studentAttendanceTotals($record)['hours']),
                        ]),

                    TextInput::make('evaluation_score')
                        ->label(__('Grade (out of :max)', ['max' => $this->maxGrade()]))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue($this->maxGrade())
                        ->required(),
                ])
                ->fillForm(fn (StudentCompany $record): array => [
                    'evaluation_score' => $record->evaluation_score,
                ])
                ->modalHeading(fn (): string => __('Grade (out of :max)', ['max' => $this->maxGrade()]))
                ->modalSubmitActionLabel(__('Save'))
                ->visible(fn (): bool => auth()->user()->can('EvaluationSupervisorStudent Grade'))
                ->action(function (StudentCompany $record, array $data): void {
                    $record->update([
                        'evaluation_score' => min((int) $data['evaluation_score'], $this->maxGrade()),
                    ]);

                    Toaster::success(__('Grade saved successfully'));
                }),
        ];
    }

    /**
     * أيام الحضور والساعات الفعلية للطالب في كل الشركات التي تدرّب فيها.
     *
     * @return array{days: int, hours: float}
     */
    protected function studentAttendanceTotals(StudentCompany $record): array
    {
        $placements = StudentCompany::query()
            ->where('student_id', $record->student_id)
            ->withAttendanceDays()
            ->withActualWorkingHours()
            ->get();

        return [
            'days' => (int) $placements->sum('attendance_days'),
            'hours' => round((float) $placements->sum('actual_working_hours'), 2),
        ];
    }

    /** العلامة القصوى لمشرف التقييم كما هي محددة في الإعدادات. */
    protected function maxGrade(): int
    {
        return app(GeneralSettings::class)->evaluation_supervisor_max_grade;
    }

    protected function evaluationAssignedQuery(): Builder
    {
        return StudentCompany::query()
            ->whereNotNull('evaluation_supervisor_id')
            ->when(
                $this->shouldScopeToAuthenticatedSupervisor(),
                fn (Builder $query): Builder => $query->where('evaluation_supervisor_id', auth()->id())
            );
    }

    protected function shouldScopeToAuthenticatedSupervisor(): bool
    {
        $user = auth()->user();

        return (bool) (
            $user?->hasRole(UserRole::EVALUATION_SUPERVISOR->value)
            && ! $user?->hasAnyRole([
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN->value,
            ])
        );
    }

    public function render()
    {
        return view('ppuds::livewire.pages.evaluation-supervisor-student.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Evaluation Supervisor Students'), 'url' => route('evaluation-supervisor-students.index')],
            ],
        ]);
    }
}

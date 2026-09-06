<?php

namespace Modules\PPUDS\Livewire\Pages\EvaluationSupervisorStudent;

use App\View\Components\AppLayout;
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
use Modules\PPUDS\Entities\StudentCompany;

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
                    ->placeholder('---')
                    ->toggleable(),

                TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'company.translations',
                        fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                    ))
                    ->placeholder('---'),

                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->placeholder('---')
                    ->toggleable(),

                TextColumn::make('department.name')
                    ->label(__('Department'))
                    ->placeholder('---')
                    ->toggleable(isToggledHiddenByDefault: true),

                UserColumn::make('evaluationSupervisor.name')
                    ->label(__('Evaluation Supervisor'))
                    ->user(fn (StudentCompany $record) => $record->evaluationSupervisor)
                    ->linksToSupervisor()
                    ->toggleable()
                    ->visible(fn (): bool => ! $this->shouldScopeToAuthenticatedSupervisor()),

                TextColumn::make('evaluation_score')
                    ->label(__('Grade (out of 25)'))
                    ->badge()
                    ->color(fn (?int $state): string => $state === null ? 'gray' : 'success')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? __('Not graded yet') : "{$state} / 25"),
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
            Action::make('grade')
                ->label(fn (StudentCompany $record): string => $record->evaluation_score === null ? __('Set Grade') : __('Grade'))
                ->icon('heroicon-o-star')
                ->color('primary')
                ->form([
                    TextInput::make('evaluation_score')
                        ->label(__('Grade (out of 25)'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(25)
                        ->required(),
                ])
                ->fillForm(fn (StudentCompany $record): array => [
                    'evaluation_score' => $record->evaluation_score,
                ])
                ->modalHeading(__('Grade (out of 25)'))
                ->modalSubmitActionLabel(__('Save'))
                ->visible(fn (): bool => auth()->user()->can('EvaluationSupervisorStudent Grade'))
                ->action(function (StudentCompany $record, array $data): void {
                    $record->update(['evaluation_score' => (int) $data['evaluation_score']]);

                    Toaster::success(__('Grade saved successfully'));
                }),
        ];
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

<?php

namespace Modules\PPUDS\Livewire\Pages\EvaluationSupervisor;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\PPUDS\Entities\StudentCompany;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * شاشة واحدة لكل ما يخص حسابات مشرفي التقييم: الإضافة والتعديل والحذف
 * والاستعادة، مع ملخص لتقدّم كل مشرف في رصد علامات طلابه.
 *
 * مشرف التقييم يرى طلابه عبر الحقل evaluation_supervisor_id على التدريب
 * (students_companies)، ويُسند إليه الطلاب من شاشة تدريب الطلاب.
 */
class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => User::query()
                ->with(['media', 'roles'])
                ->whereHas('roles', fn (Builder $query) => $query->where('name', UserRole::EVALUATION_SUPERVISOR->value))
                // الأعداد تُحسب في الاستعلام نفسه بدل استعلام لكل صف.
                ->select('users.*')
                ->selectSub($this->placementsOfSupervisor()->selectRaw('COUNT(DISTINCT student_id)'), 'assigned_students_count')
                ->selectSub($this->placementsOfSupervisor()->selectRaw('COUNT(*)'), 'assigned_trainings_count')
                ->selectSub($this->placementsOfSupervisor()->selectRaw('COUNT(evaluation_score)'), 'graded_trainings_count'))
            ->columns([
                UserColumn::make('name')
                    ->label(__('Name'))
                    ->user(fn (User $record) => $record)
                    ->subtitle(fn (User $record): ?string => $record->email)
                    ->linksToEvaluationSupervisor()
                    ->searchable(['name', 'email'])
                    ->sortable(),

                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('assigned_students_count')
                    ->label(__('Assigned Students'))
                    ->alignCenter()
                    ->badge()
                    ->color(fn (User $record): string => (int) $record->assigned_students_count > 0 ? 'primary' : 'gray')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('assigned_students_count', $direction)),

                TextColumn::make('grading_progress')
                    ->label(__('Grading Progress'))
                    ->alignCenter()
                    ->badge()
                    ->getStateUsing(fn (User $record): string => (int) $record->graded_trainings_count.' / '.(int) $record->assigned_trainings_count)
                    ->color(fn (User $record): string => $this->progressColor($record))
                    ->description(fn (User $record): ?string => (int) $record->assigned_trainings_count > 0
                        ? __('Completion').': '.$this->completionPercent($record).'%'
                        : null),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->label(__('Status'))
                    ->badge()
                    ->getStateUsing(fn (User $record): string => $record->trashed() ? __('Deleted') : __('Active'))
                    ->color(fn (User $record): string => $record->trashed() ? 'danger' : 'success'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->headerActions([$this->createAction()])
            ->actions([
                $this->detailsAction(),
                $this->editAction(),
                $this->deleteAction(),
                $this->restoreAction(),
            ])
            ->bulkActions([])
            ->emptyStateHeading(__('No evaluation supervisors yet'))
            ->emptyStateDescription(__('Add an evaluation supervisor, then assign students to them from the Student Companies list.'));
    }

    // ===================== الفلاتر =====================

    /**
     * @return array<int, mixed>
     */
    protected function getTableFilters(): array
    {
        return [
            Filter::make('supervisor_details')
                ->label(__('Search Details'))
                ->form([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->live(debounce: 500)
                        ->prefixIcon('solar-user-linear'),

                    TextInput::make('email')
                        ->label(__('Email'))
                        ->live(debounce: 500)
                        ->prefixIcon('solar-letter-linear'),

                    TextInput::make('phone')
                        ->label(__('Phone'))
                        ->live(debounce: 500)
                        ->prefixIcon('solar-phone-linear'),
                ])
                ->columns(3)
                ->columnSpan(2)
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['name'] ?? null, fn (Builder $q, string $v) => $q->where('users.name', 'like', "%{$v}%"))
                    ->when($data['email'] ?? null, fn (Builder $q, string $v) => $q->where('users.email', 'like', "%{$v}%"))
                    ->when($data['phone'] ?? null, fn (Builder $q, string $v) => $q->where('users.phone', 'like', "%{$v}%"))),

            // المشرف بلا طلاب لا يرى أحداً، فهذا الفلتر يكشف الحسابات التي تنتظر الإسناد.
            TernaryFilter::make('has_students')
                ->label(__('Assignment Status'))
                ->placeholder(__('All'))
                ->trueLabel(__('With Assigned Students'))
                ->falseLabel(__('Without Assigned Students'))
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereIn('users.id', $this->supervisorIdsOf(StudentCompany::query())),
                    false: fn (Builder $query): Builder => $query->whereNotIn('users.id', $this->supervisorIdsOf(StudentCompany::query())),
                    blank: fn (Builder $query): Builder => $query,
                ),

            // لمتابعة المشرفين الذين لم يُكملوا رصد العلامات بعد.
            TernaryFilter::make('grading_status')
                ->label(__('Grading Status'))
                ->placeholder(__('All'))
                ->trueLabel(__('Has Pending Grades'))
                ->falseLabel(__('All Grades Entered'))
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereIn(
                        'users.id',
                        $this->supervisorIdsOf(StudentCompany::query()->whereNull('evaluation_score'))
                    ),
                    false: fn (Builder $query): Builder => $query
                        ->whereIn('users.id', $this->supervisorIdsOf(StudentCompany::query()))
                        ->whereNotIn('users.id', $this->supervisorIdsOf(StudentCompany::query()->whereNull('evaluation_score'))),
                    blank: fn (Builder $query): Builder => $query,
                ),

            TernaryFilter::make('deleted_at')
                ->label(__('Deleted Users'))
                ->placeholder(__('Without Deleted'))
                ->trueLabel(__('Deleted Only'))
                ->falseLabel(__('With Deleted'))
                ->queries(
                    true: fn (Builder $query): Builder => $query->onlyTrashed(),
                    false: fn (Builder $query): Builder => $query->withTrashed(),
                    blank: fn (Builder $query): Builder => $query->withoutTrashed(),
                )
                // الفلاتر تُطبَّق داخل where متداخل لا يستطيع إلغاء نطاق الحذف الناعم،
                // فيُرفع النطاق من الاستعلام الأساسي كما يفعل TrashedFilter في Filament.
                ->baseQuery(fn (Builder $query): Builder => $query->withoutGlobalScopes([SoftDeletingScope::class])),
        ];
    }

    // ===================== الإجراءات =====================

    protected function createAction(): Action
    {
        return Action::make('createEvaluationSupervisor')
            ->label(__('Add Evaluation Supervisor'))
            ->icon('solar-user-plus-bold-duotone')
            ->color('primary')
            ->modalHeading(__('Add Evaluation Supervisor'))
            ->modalDescription(__('The supervisor signs in with this email. After saving, assign students to them from the Student Companies list.'))
            ->modalSubmitActionLabel(__('Save'))
            ->modalWidth('2xl')
            ->form([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->maxLength(255)
                        ->prefixIcon('solar-user-linear'),

                    TextInput::make('name_en')
                        ->label(__('Name (English)'))
                        ->maxLength(255)
                        ->prefixIcon('solar-user-linear'),

                    TextInput::make('phone')
                        ->label(__('Phone'))
                        ->required()
                        ->maxLength(255)
                        ->helperText(__('It will also be the initial password.'))
                        ->prefixIcon('solar-phone-linear'),

                    TextInput::make('email')
                        ->label(__('Email'))
                        ->email()
                        ->required()
                        ->unique('users', 'email')
                        ->maxLength(255)
                        ->helperText(__('If the email is rejected, a deleted user may still hold it — check the deleted filter and restore them.'))
                        ->prefixIcon('solar-letter-linear'),
                ]),
            ])
            ->visible(fn (): bool => $this->canCreate())
            ->action(function (array $data): void {
                abort_unless($this->canCreate(), 403);

                try {
                    DB::transaction(function () use ($data): void {
                        $user = User::create([
                            'name' => $data['name'],
                            'name_en' => $data['name_en'] ?? null,
                            'email' => $data['email'],
                            'phone' => $data['phone'],
                            'password' => Hash::make((string) $data['phone']),
                        ]);

                        $user->assignRole(Role::findOrCreate(UserRole::EVALUATION_SUPERVISOR->value, 'web'));
                        $user->generateAvatar();
                    });
                } catch (Throwable $exception) {
                    report($exception);

                    Toaster::error(__('Failed to add the supervisor. Please try again.'));

                    return;
                }

                Toaster::success(__('Evaluation supervisor added successfully'));
            });
    }

    protected function detailsAction(): Action
    {
        return Action::make('details')
            ->label(__('Details'))
            ->icon('solar-eye-bold-duotone')
            ->color('primary')
            ->url(fn (User $record): string => route('evaluation-supervisors.details', $record))
            ->visible(fn (): bool => (bool) auth()->user()?->can('EvaluationSupervisor Details List'));
    }

    protected function editAction(): Action
    {
        return Action::make('editEvaluationSupervisor')
            ->label(__('Edit'))
            ->icon('solar-pen-bold-duotone')
            ->color('gray')
            ->modalHeading(__('Edit Evaluation Supervisor'))
            ->modalSubmitActionLabel(__('Save'))
            ->modalWidth('2xl')
            ->fillForm(fn (User $record): array => [
                'name' => $record->name,
                'name_en' => $record->name_en,
                'email' => $record->email,
                'phone' => $record->phone,
            ])
            ->form(fn (User $record): array => [
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->maxLength(255),

                    TextInput::make('name_en')
                        ->label(__('Name (English)'))
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label(__('Phone'))
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label(__('Email'))
                        ->email()
                        ->required()
                        ->unique('users', 'email', ignorable: $record)
                        ->maxLength(255),

                    TextInput::make('password')
                        ->label(__('New Password'))
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->helperText(__('Leave it empty to keep the current password.')),
                ]),
            ])
            ->visible(fn (User $record): bool => $this->canUpdate() && ! $record->trashed())
            ->action(function (array $data, User $record): void {
                abort_unless($this->canUpdate(), 403);

                $record->fill([
                    'name' => $data['name'],
                    'name_en' => $data['name_en'] ?? null,
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                ]);

                if (filled($data['password'] ?? null)) {
                    $record->password = Hash::make((string) $data['password']);
                }

                $record->save();

                Toaster::success(__('Evaluation supervisor updated successfully'));
            });
    }

    protected function deleteAction(): Action
    {
        return Action::make('deleteEvaluationSupervisor')
            ->label(__('Delete'))
            ->icon('solar-trash-bin-trash-bold-duotone')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('Delete Evaluation Supervisor'))
            ->modalDescription(__('The supervisor is hidden, not erased, and can be restored later. Their assigned students and the grades they entered are kept.'))
            ->modalSubmitActionLabel(__('Delete'))
            ->visible(fn (User $record): bool => $this->canDelete()
                && ! $record->trashed()
                && ! $record->is(auth()->user()))
            ->action(function (User $record): void {
                abort_unless($this->canDelete() && ! $record->is(auth()->user()), 403);

                // الأدوار تبقى حتى تعود الصلاحيات كما كانت عند الاستعادة،
                // والجلسات تُبطَل فوراً لمنع الدخول بعد الحذف.
                $record->tokens()->delete();
                $record->delete();

                Toaster::success(__('Evaluation supervisor deleted successfully'));
            });
    }

    protected function restoreAction(): Action
    {
        return Action::make('restoreEvaluationSupervisor')
            ->label(__('Restore'))
            ->icon('solar-history-bold-duotone')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('Restore Evaluation Supervisor'))
            ->modalDescription(__('The supervisor will regain access with the same role and assigned students they had before deletion.'))
            ->modalSubmitActionLabel(__('Restore'))
            ->visible(fn (User $record): bool => $this->canDelete() && $record->trashed())
            ->action(function (User $record): void {
                abort_unless($this->canDelete(), 403);

                $record->restore();

                Toaster::success(__('Evaluation supervisor restored successfully'));
            });
    }

    // ===================== مساعدات =====================

    /**
     * تدريبات المشرف الحالي في الصف، أساساً للأعداد المحسوبة داخل الاستعلام.
     */
    protected function placementsOfSupervisor(): Builder
    {
        return StudentCompany::query()->whereColumn('evaluation_supervisor_id', 'users.id');
    }

    /**
     * أرقام المشرفين المسندين في مجموعة تدريبات، لاستعمالها داخل whereIn.
     */
    protected function supervisorIdsOf(Builder $placements): Builder
    {
        return $placements
            ->whereNotNull('evaluation_supervisor_id')
            ->select('evaluation_supervisor_id');
    }

    protected function completionPercent(User $record): int
    {
        $trainings = (int) $record->assigned_trainings_count;

        return $trainings > 0
            ? (int) round(((int) $record->graded_trainings_count / $trainings) * 100)
            : 0;
    }

    protected function progressColor(User $record): string
    {
        $trainings = (int) $record->assigned_trainings_count;

        if ($trainings === 0) {
            return 'gray';
        }

        return (int) $record->graded_trainings_count >= $trainings ? 'success' : 'warning';
    }

    protected function canCreate(): bool
    {
        return (bool) auth()->user()?->can('EvaluationSupervisor Create');
    }

    protected function canUpdate(): bool
    {
        return (bool) auth()->user()?->can('EvaluationSupervisor Update');
    }

    protected function canDelete(): bool
    {
        return (bool) auth()->user()?->can('EvaluationSupervisor Delete');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.evaluation-supervisor.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Evaluation Supervisors List'), 'url' => route('evaluation-supervisors.index')],
            ],
        ]);
    }
}

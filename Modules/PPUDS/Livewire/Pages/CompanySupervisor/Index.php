<?php

namespace Modules\PPUDS\Livewire\Pages\CompanySupervisor;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Services\PpuApiService;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * شاشة واحدة لكل ما يخص مشرفي الشركات: الإضافة والتعديل وإسناد الأقسام
 * وسحبها والحذف والاستعادة.
 *
 * مشرف الشركة يرى الطلاب عبر مقعد في جدول branch_department يربط
 * (فرع + قسم ← مستخدم)، لا عبر حقل على الطالب. لذلك «الإسناد» هنا يعني
 * إجلاس المشرف في ذلك المقعد، وكل طلاب القسم ينتقلون معه ببياناتهم كاملة.
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
                ->whereHas('roles', fn (Builder $query) => $query->where('name', UserRole::COMPANY_SUPERVISOR->value)))
            ->columns([
                UserColumn::make('name')
                    ->label(__('Name'))
                    ->user(fn (User $record) => $record)
                    ->subtitle(fn (User $record): ?string => $record->email)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('company')
                    ->label(__('Company'))
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (User $record): array => $this->companyLabels($record))
                    ->placeholder(__('Not assigned to any department')),

                TextColumn::make('assignments')
                    ->label(__('Assigned Departments'))
                    ->badge()
                    ->color('primary')
                    ->getStateUsing(fn (User $record): array => $this->assignmentLabels($record))
                    ->placeholder(__('Not assigned to any department')),

                TextColumn::make('students')
                    ->label(__('Supervised Students'))
                    ->alignCenter()
                    ->badge()
                    ->color(fn (User $record): string => $this->supervisedStudentsCount($record) > 0 ? 'success' : 'gray')
                    ->getStateUsing(fn (User $record): int => $this->supervisedStudentsCount($record)),

                TextColumn::make('deleted_at')
                    ->label(__('Status'))
                    ->badge()
                    ->getStateUsing(fn (User $record): string => $record->trashed() ? __('Deleted') : __('Active'))
                    ->color(fn (User $record): string => $record->trashed() ? 'danger' : 'success'),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->headerActions([$this->createAction()])
            ->actions([
                $this->assignAction(),
                $this->unassignAction(),
                $this->editAction(),
                $this->deleteAction(),
                $this->restoreAction(),
            ])
            ->bulkActions([])
            ->emptyStateHeading(__('No company supervisors yet'))
            ->emptyStateDescription(__('Add a supervisor and assign them to a department so they can see their students.'));
    }

    // ===================== الفلاتر =====================

    /**
     * الشركة والفرع والقسم ليست حقولاً على المستخدم بل مقاعد في جدول الربط،
     * فكل فلتر منها يُترجم إلى EXISTS على branch_department.
     *
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
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['name'] ?? null, fn (Builder $q, string $v) => $q->where('users.name', 'like', "%{$v}%"))
                    ->when($data['email'] ?? null, fn (Builder $q, string $v) => $q->where('users.email', 'like', "%{$v}%"))
                    ->when($data['phone'] ?? null, fn (Builder $q, string $v) => $q->where('users.phone', 'like', "%{$v}%"))),

            SelectFilter::make('company')
                ->label(__('Company'))
                ->options(fn (): array => Company::query()->get()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['value'] ?? null,
                    fn (Builder $q, $companyId) => $q->whereExists(fn ($sub) => $this->seatSubQuery($sub)
                        ->join(config('ppuds.table_prefix').'branch_company as bc', 'bc.branch_id', '=', 'bd.branch_id')
                        ->where('bc.company_id', $companyId))
                )),

            SelectFilter::make('branch')
                ->label(__('Branch'))
                ->options(fn (): array => Branch::query()->get()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['value'] ?? null,
                    fn (Builder $q, $branchId) => $q->whereExists(fn ($sub) => $this->seatSubQuery($sub)
                        ->where('bd.branch_id', $branchId))
                )),

            SelectFilter::make('department')
                ->label(__('Department'))
                ->options(fn (): array => CompanyDepartment::query()->get()->pluck('name', 'id')->toArray())
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['value'] ?? null,
                    fn (Builder $q, $departmentId) => $q->whereExists(fn ($sub) => $this->seatSubQuery($sub)
                        ->where('bd.company_department_id', $departmentId))
                )),

            // المشرف بلا قسم لا يرى أي طالب، فهذا الفلتر يكشف الحسابات المعلّقة.
            TernaryFilter::make('has_assignment')
                ->label(__('Assignment Status'))
                ->placeholder(__('All'))
                ->trueLabel(__('Assigned To A Department'))
                ->falseLabel(__('Without Any Department'))
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereExists(fn ($sub) => $this->seatSubQuery($sub)),
                    false: fn (Builder $query): Builder => $query->whereNotExists(fn ($sub) => $this->seatSubQuery($sub)),
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
                ),
        ];
    }

    /**
     * مقاعد المستخدم الحالي في جدول الربط، أساساً لكل فلاتر الإسناد.
     */
    protected function seatSubQuery(mixed $subQuery): mixed
    {
        return $subQuery
            ->select(DB::raw(1))
            ->from(config('ppuds.table_prefix').'branch_department as bd')
            ->whereColumn('bd.user_id', 'users.id');
    }

    // ===================== الإجراءات =====================

    /**
     * الإضافة والإسناد في خطوة واحدة: المشرف بلا قسم لا يرى أحداً،
     * فطلب القسم هنا يمنع أكثر خطأ متكرر.
     */
    protected function createAction(): Action
    {
        return Action::make('createCompanySupervisor')
            ->label(__('Add Company Supervisor'))
            ->icon('solar-user-plus-bold-duotone')
            ->color('primary')
            ->modalHeading(__('Add Company Supervisor'))
            ->modalDescription(__('Enter the supervisor details, then choose the department they will supervise.'))
            ->modalSubmitActionLabel(__('Save'))
            ->modalWidth('2xl')
            ->form([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
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
                        ->columnSpanFull()
                        ->helperText(__('If the email is rejected, a deleted user may still hold it — check the deleted filter and restore them.'))
                        ->prefixIcon('solar-letter-linear'),
                ]),

                ...$this->departmentPickerFields(),
            ])
            ->visible(fn (): bool => $this->canManage())
            ->action(function (array $data): void {
                abort_unless($this->canManage(), 403);

                try {
                    $user = DB::transaction(function () use ($data): User {
                        $user = User::create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'phone' => $data['phone'],
                            'password' => Hash::make((string) $data['phone']),
                        ]);

                        $user->assignRole(Role::findOrCreate(UserRole::COMPANY_SUPERVISOR->value, 'web'));
                        $user->generateAvatar();

                        $this->seatSupervisor((int) $data['branch_id'], (int) $data['department_id'], $user->id);

                        return $user;
                    });
                } catch (Throwable $exception) {
                    report($exception);

                    Toaster::error(__('Failed to add the supervisor. Please try again.'));

                    return;
                }

                Toaster::success(__('Company supervisor added and assigned successfully'));

                // خارج المعاملة عمداً: نداء شبكي للجامعة، وفشله يجب ألا يُلغي
                // إنشاء الحساب محلياً. كلمة المرور الأولى هي رقم الهاتف.
                $this->sendSupervisorToUniversity(
                    (int) $data['company_id'],
                    $user->id,
                    (string) $data['phone'],
                );
            });
    }

    protected function assignAction(): Action
    {
        return Action::make('assignDepartment')
            ->label(__('Assign Department'))
            ->icon('solar-add-circle-bold-duotone')
            ->color('success')
            ->modalHeading(fn (User $record): string => __('Assign Department To :name', ['name' => $record->name]))
            ->modalDescription(__('The supervisor will immediately see every student in this department, with their full history.'))
            ->modalSubmitActionLabel(__('Assign'))
            ->modalWidth('2xl')
            ->form($this->departmentPickerFields())
            ->visible(fn (User $record): bool => $this->canManage() && ! $record->trashed())
            ->action(function (array $data, User $record): void {
                abort_unless($this->canManage(), 403);

                $this->seatSupervisor((int) $data['branch_id'], (int) $data['department_id'], $record->id);

                Toaster::success(__('Department assigned successfully'));

                // المشرف قد يكون جديداً على هذه الشركة في نظام الجامعة أيضاً،
                // فنُرسله عند الإسناد لا عند الإنشاء فقط. كلمة المرور تُترك
                // فارغة ليستعمل النظام رقم الهاتف كما يفعل في المزامنة العامة.
                $this->sendSupervisorToUniversity((int) $data['company_id'], $record->id, null);
            });
    }

    protected function unassignAction(): Action
    {
        return Action::make('unassignDepartment')
            ->label(__('Remove Assignment'))
            ->icon('solar-minus-circle-bold-duotone')
            ->color('warning')
            ->modalHeading(fn (User $record): string => __('Remove Assignment From :name', ['name' => $record->name]))
            ->modalDescription(__('The seat becomes vacant and its students will not be visible to any company supervisor until someone else is assigned.'))
            ->modalSubmitActionLabel(__('Remove'))
            ->requiresConfirmation()
            ->form(fn (User $record): array => [
                Select::make('assignment')
                    ->label(__('Assigned Departments'))
                    ->options($this->assignmentOptions($record))
                    ->required()
                    ->native(false),
            ])
            ->visible(fn (User $record): bool => $this->canManage()
                && ! $record->trashed()
                && filled($this->assignmentOptions($record)))
            ->action(function (array $data, User $record): void {
                abort_unless($this->canManage(), 403);

                [$branchId, $departmentId] = array_map('intval', explode('-', (string) $data['assignment']));

                // المقعد يبقى موجوداً بمشرف فارغ حتى لا يختفي القسم من الشاشة،
                // فيمكن إسناد بديل لاحقاً بضغطة واحدة.
                DB::table(config('ppuds.table_prefix').'branch_department')
                    ->where('branch_id', $branchId)
                    ->where('company_department_id', $departmentId)
                    ->where('user_id', $record->id)
                    ->update(['user_id' => null, 'updated_at' => now()]);

                Toaster::success(__('Assignment removed successfully'));
            });
    }

    protected function editAction(): Action
    {
        return Action::make('editCompanySupervisor')
            ->label(__('Edit'))
            ->icon('solar-pen-bold-duotone')
            ->color('gray')
            ->modalHeading(__('Edit Company Supervisor'))
            ->modalSubmitActionLabel(__('Save'))
            ->fillForm(fn (User $record): array => [
                'name' => $record->name,
                'email' => $record->email,
                'phone' => $record->phone,
            ])
            ->form(fn (User $record): array => [
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->maxLength(255),

                    // إجباري: نظام الجامعة يبني رقم الجوال من هذا الحقل،
                    // وفراغه يُسقط إرسال المشرف إليه بصمت.
                    TextInput::make('phone')
                        ->label(__('Phone'))
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label(__('Email'))
                        ->email()
                        ->required()
                        ->unique('users', 'email', ignorable: $record)
                        ->maxLength(255)
                        ->columnSpanFull(),

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
            ->visible(fn (User $record): bool => $this->canManage() && ! $record->trashed())
            ->action(function (array $data, User $record): void {
                abort_unless($this->canManage(), 403);

                $record->fill([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                ]);

                if (filled($data['password'] ?? null)) {
                    $record->password = Hash::make((string) $data['password']);
                }

                $record->save();

                Toaster::success(__('Company supervisor updated successfully'));
            });
    }

    protected function deleteAction(): Action
    {
        return Action::make('deleteCompanySupervisor')
            ->label(__('Delete'))
            ->icon('solar-trash-bin-trash-bold-duotone')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('Delete Company Supervisor'))
            ->modalDescription(__('The supervisor is hidden, not erased, and can be restored later. Their departments stay in place and their field visits are kept.'))
            ->modalSubmitActionLabel(__('Delete'))
            ->visible(fn (User $record): bool => $this->canManage() && ! $record->trashed())
            ->action(function (User $record): void {
                abort_unless($this->canManage(), 403);

                // الأدوار تبقى حتى تعود الصلاحيات كما كانت عند الاستعادة،
                // والجلسات تُبطَل فوراً لمنع الدخول بعد الحذف.
                $record->tokens()->delete();
                $record->delete();

                Toaster::success(__('Company supervisor deleted successfully'));
            });
    }

    protected function restoreAction(): Action
    {
        return Action::make('restoreCompanySupervisor')
            ->label(__('Restore'))
            ->icon('solar-history-bold-duotone')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('Restore Company Supervisor'))
            ->modalDescription(__('The supervisor will regain access with the same roles and departments they had before deletion.'))
            ->modalSubmitActionLabel(__('Restore'))
            ->visible(fn (User $record): bool => $this->canManage() && $record->trashed())
            ->action(function (User $record): void {
                abort_unless($this->canManage(), 403);

                $record->restore();

                Toaster::success(__('Company supervisor restored successfully'));
            });
    }

    // ===================== حقول اختيار القسم =====================

    /**
     * شركة ← فرع ← قسم، كل حقل يُفتح بعد سابقه ويُصفّر ما بعده.
     * الحقل الأخير يوضّح من يشغل المقعد حالياً قبل استبداله.
     *
     * @return array<int, mixed>
     */
    protected function departmentPickerFields(): array
    {
        return [
            Select::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => Company::query()->get()->pluck('name', 'id')->toArray())
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('branch_id', null);
                    $set('department_id', null);
                })
                ->prefixIcon('solar-city-linear'),

            Select::make('branch_id')
                ->label(__('Branch'))
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('department_id', null))
                ->disabled(fn (Get $get): bool => blank($get('company_id')))
                ->placeholder(fn (Get $get): string => filled($get('company_id')) ? __('Select Branch') : __('Select Company First'))
                ->options(fn (Get $get): array => blank($get('company_id'))
                    ? []
                    : Branch::query()
                        ->whereHas('companies', fn (Builder $query) => $query->whereKey($get('company_id')))
                        ->get()
                        ->pluck('name', 'id')
                        ->toArray())
                ->prefixIcon('solar-map-point-linear'),

            Select::make('department_id')
                ->label(__('Department'))
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->disabled(fn (Get $get): bool => blank($get('branch_id')))
                ->placeholder(fn (Get $get): string => filled($get('branch_id')) ? __('Select Department') : __('Select Branch First'))
                ->options(fn (Get $get): array => blank($get('branch_id'))
                    ? []
                    : CompanyDepartment::query()
                        ->whereHas('branches', fn (Builder $query) => $query->whereKey($get('branch_id')))
                        ->get()
                        ->pluck('name', 'id')
                        ->toArray())
                ->prefixIcon('solar-users-group-two-rounded-linear'),

            Placeholder::make('current_seat_holder')
                ->label(__('Current Supervisor Of This Department'))
                ->content(fn (Get $get): HtmlString => $this->seatHolderNotice($get('branch_id'), $get('department_id')))
                ->visible(fn (Get $get): bool => filled($get('department_id'))),
        ];
    }

    /**
     * تحذير صريح قبل الاستبدال: المقعد يتسع لمشرف واحد، والإسناد يزيح من فيه.
     */
    protected function seatHolderNotice(mixed $branchId, mixed $departmentId): HtmlString
    {
        if (blank($branchId) || blank($departmentId)) {
            return new HtmlString('');
        }

        $holderId = DB::table(config('ppuds.table_prefix').'branch_department')
            ->where('branch_id', $branchId)
            ->where('company_department_id', $departmentId)
            ->value('user_id');

        if (blank($holderId)) {
            return new HtmlString(
                '<span class="text-sm font-medium text-success-600">'.e(__('Vacant — no supervisor assigned')).'</span>'
            );
        }

        $holder = User::withTrashed()->find($holderId);

        return new HtmlString(
            '<span class="text-sm font-medium text-warning-600">'
            .e(__(':name is currently assigned and will be replaced.', ['name' => $holder?->name ?? '—']))
            .'</span>'
        );
    }

    // ===================== مساعدات =====================

    /**
     * إرسال المشرف إلى نظام الجامعة عبر نقطة إضافة الشركة، وهي نفس الطريقة
     * التي تستخدمها شاشة تفاصيل الشركة.
     *
     * الـ payload يبني رقم الجوال من هاتف المشرف ويسقط الإرسال كلياً إن كان
     * فارغاً، ولذلك الهاتف إجباري عند الإضافة. وكلمة المرور إن لم تُمرَّر
     * يستعمل النظام رقم الهاتف بدلاً منها.
     */
    protected function sendSupervisorToUniversity(int $companyId, int $supervisorId, ?string $plainPassword): void
    {
        $company = Company::query()
            ->with(['branches.supervisors', 'translations'])
            ->find($companyId);

        if (! $company) {
            return;
        }

        try {
            $result = app(PpuApiService::class)->addCompanyToUniversity(
                $company,
                $plainPassword,
                $supervisorId,
                sendEvenIfCompanyExists: true,
            );
        } catch (Throwable $exception) {
            report($exception);

            Toaster::error(__('Unable to send company supervisor to university system'));

            return;
        }

        if (($result['operation'] ?? null) === 'already_exists') {
            Toaster::success(__('Company supervisor already exists in university system'));

            return;
        }

        if ($result === null || ($result['success'] ?? null) === false) {
            Toaster::error(__('Unable to send company supervisor to university system'));

            return;
        }

        Toaster::success(__('Company supervisor sent to university successfully'));
    }

    /**
     * المقعد واحد لكل (فرع + قسم): نُحدِّث الصف إن وُجد وننشئه إن كان مفقوداً.
     * الصف قد يكون مفقوداً تماماً بسبب عمليات حذف قديمة كانت تُسقطه.
     */
    protected function seatSupervisor(int $branchId, int $departmentId, int $userId): void
    {
        $pivot = config('ppuds.table_prefix').'branch_department';

        $exists = DB::table($pivot)
            ->where('branch_id', $branchId)
            ->where('company_department_id', $departmentId)
            ->exists();

        if ($exists) {
            DB::table($pivot)
                ->where('branch_id', $branchId)
                ->where('company_department_id', $departmentId)
                ->update(['user_id' => $userId, 'updated_at' => now()]);

            return;
        }

        DB::table($pivot)->insert([
            'branch_id' => $branchId,
            'company_department_id' => $departmentId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * الشركات التي يشرف فيها، بلا تكرار — قد يكون مسنداً لعدة أقسام
     * في الشركة نفسها.
     *
     * @return array<int, string>
     */
    protected function companyLabels(User $supervisor): array
    {
        return $this->assignmentRows($supervisor)
            ->pluck('company_name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function assignmentLabels(User $supervisor): array
    {
        return $this->assignmentRows($supervisor)
            ->map(fn (object $row): string => trim(($row->branch_name ?? '—').' › '.($row->department_name ?? '—')))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function assignmentOptions(User $supervisor): array
    {
        return $this->assignmentRows($supervisor)
            ->mapWithKeys(fn (object $row): array => [
                $row->branch_id.'-'.$row->company_department_id => trim(($row->branch_name ?? '—').' › '.($row->department_name ?? '—')),
            ])
            ->all();
    }

    /**
     * أسماء الفروع والأقسام مترجمة في جداول منفصلة، فنقرأها بانضمام مباشر
     * لتفادي استعلام لكل صف في الجدول.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function assignmentRows(User $supervisor): \Illuminate\Support\Collection
    {
        $prefix = config('ppuds.table_prefix');
        $locale = app()->getLocale();

        return DB::table($prefix.'branch_department as bd')
            ->where('bd.user_id', $supervisor->id)
            ->leftJoin('branch_branch_translations as bt', function ($join) use ($locale): void {
                $join->on('bt.branch_id', '=', 'bd.branch_id')->where('bt.locale', '=', $locale);
            })
            ->leftJoin($prefix.'company_department_translations as dt', function ($join) use ($locale): void {
                $join->on('dt.department_id', '=', 'bd.company_department_id')->where('dt.locale', '=', $locale);
            })
            // الشركة تصل للمقعد عبر الفرع، لا مباشرةً
            ->leftJoin($prefix.'branch_company as bc', 'bc.branch_id', '=', 'bd.branch_id')
            ->leftJoin($prefix.'company_translations as ct', function ($join) use ($locale): void {
                $join->on('ct.company_id', '=', 'bc.company_id')->where('ct.locale', '=', $locale);
            })
            ->select([
                'bd.branch_id',
                'bd.company_department_id',
                'bt.name as branch_name',
                'dt.name as department_name',
                'bc.company_id',
                'ct.name as company_name',
            ])
            ->get();
    }

    protected function supervisedStudentsCount(User $supervisor): int
    {
        $prefix = config('ppuds.table_prefix');
        $table = (new StudentCompany)->getTable();

        return StudentCompany::query()
            ->whereNotNull('branch_id')
            ->whereNotNull('department_id')
            ->whereExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from($prefix.'branch_department')
                ->whereColumn($prefix.'branch_department.branch_id', "{$table}.branch_id")
                ->whereColumn($prefix.'branch_department.company_department_id', "{$table}.department_id")
                ->where($prefix.'branch_department.user_id', $supervisor->id))
            ->distinct('student_id')
            ->count('student_id');
    }

    protected function canManage(): bool
    {
        return (bool) auth()->user()?->can('Company Update');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.company-supervisor.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Company Supervisors'), 'url' => route('company-supervisors.index')],
            ],
        ]);
    }
}

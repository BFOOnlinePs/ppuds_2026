<?php

namespace Modules\Core\Livewire\Pages\ActivityLog;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Maatwebsite\Excel\Excel as WriterType;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Exports\AuthActivityLogExport;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\Core\Listeners\AuthActivitySubscriber;
use Modules\Core\Traits\ActivityLogReporting;
use Modules\Core\Traits\PrintsTableReportPdf;
use Spatie\Permission\Models\Role;

/**
 * Sign-in report: successful logins, logouts, failed attempts and lockouts,
 * recorded by AuthActivitySubscriber under the `auth` log name.
 */
class AuthLog extends Component implements HasForms, HasTable
{
    use ActivityLogReporting;
    use InteractsWithForms;
    use InteractsWithTable;
    use PrintsTableReportPdf;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->activityQuery()->with('causer.roles'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                TextColumn::make('event')
                    ->label(__('Event'))
                    ->badge()
                    ->color(fn (?string $state): string => $this->eventColor($state))
                    ->formatStateUsing(fn (?string $state): string => $this->eventLabel($state))
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label(__('User'))
                    ->getStateUsing(fn (Model $record): string => $record->causer?->name
                        ?? (string) ($this->activityProperties($record)['login'] ?? __('Unknown User')))
                    ->description(fn (Model $record): ?string => $record->causer?->email)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHasMorph(
                        'causer',
                        [User::class],
                        fn (Builder $causer): Builder => $causer
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                    ))
                    ->weight('bold'),

                TextColumn::make('causer_roles')
                    ->label(__('Roles'))
                    ->getStateUsing(fn (Model $record): string => $this->causerRoles($record))
                    ->badge()
                    ->separator('،')
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('login')
                    ->label(__('Login Identifier'))
                    ->getStateUsing(fn (Model $record): string => (string) ($this->activityProperties($record)['login'] ?? '—'))
                    ->toggleable(),

                TextColumn::make('ip')
                    ->label(__('IP Address'))
                    ->getStateUsing(fn (Model $record): string => (string) ($this->activityProperties($record)['ip'] ?? '—'))
                    ->copyable(),

                TextColumn::make('device_name')
                    ->label(__('Device'))
                    ->getStateUsing(fn (Model $record): string => (string) ($this->activityProperties($record)['device_name'] ?? '—'))
                    ->icon('solar-smartphone-bold-duotone')
                    ->toggleable(),

                TextColumn::make('browser')
                    ->label(__('Browser'))
                    ->getStateUsing(fn (Model $record): string => (string) ($this->activityProperties($record)['browser'] ?? '—'))
                    ->toggleable(),

                TextColumn::make('platform')
                    ->label(__('Platform'))
                    ->getStateUsing(fn (Model $record): string => (string) ($this->activityProperties($record)['platform'] ?? '—'))
                    ->toggleable(),

                TextColumn::make('guard')
                    ->label(__('Guard'))
                    ->getStateUsing(fn (Model $record): string => (string) ($this->activityProperties($record)['guard'] ?? '—'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user_agent')
                    ->label(__('User Agent'))
                    ->getStateUsing(fn (Model $record): string => (string) ($this->activityProperties($record)['user_agent'] ?? '—'))
                    ->wrap()
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->headerActions($this->getTableHeaderActions())
            ->actions($this->getTableActions())
            ->bulkActions([
                DeleteBulkAction::make()
                    ->visible(fn (): bool => auth()->user()->can('Activity Log Delete')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** Only the authentication entries, whatever else the log holds. */
    protected function activityQuery(): Builder
    {
        return $this->activityModel()::query()->where('log_name', AuthActivitySubscriber::LOG_NAME);
    }

    /** Headline counts for the strip above the table. */
    #[Computed]
    public function totals(): array
    {
        $base = $this->activityQuery();

        return [
            'logins' => (clone $base)->where('event', AuthActivitySubscriber::EVENT_LOGIN)->count(),
            'logouts' => (clone $base)->where('event', AuthActivitySubscriber::EVENT_LOGOUT)->count(),
            'failed' => (clone $base)->where('event', AuthActivitySubscriber::EVENT_FAILED_LOGIN)->count(),
            'lockouts' => (clone $base)->where('event', AuthActivitySubscriber::EVENT_LOCKOUT)->count(),
            'logins_today' => (clone $base)
                ->where('event', AuthActivitySubscriber::EVENT_LOGIN)
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            'failed_today' => (clone $base)
                ->where('event', AuthActivitySubscriber::EVENT_FAILED_LOGIN)
                ->whereDate('created_at', now()->toDateString())
                ->count(),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('event')
                ->label(__('Event'))
                ->options($this->authEventOptions())
                ->multiple()
                ->native(false),

            SelectFilter::make('causer_id')
                ->label(__('User'))
                ->options(fn (): array => $this->causerOptions())
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->where('causer_id', $data['value'])->where('causer_type', (new User)->getMorphClass())
                    : $query),

            SelectFilter::make('causer_role')
                ->label(__('Roles'))
                ->options(fn (): array => $this->roleOptions())
                ->multiple()
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => $this->applyRoleFilter($query, (array) ($data['values'] ?? []))),

            Filter::make('ip')
                ->label(__('IP Address'))
                ->form([
                    TextInput::make('ip')
                        ->label(__('IP Address'))
                        ->live(debounce: 500),
                ])
                ->query(fn (Builder $query, array $data): Builder => filled($data['ip'] ?? null)
                    ? $query->where('properties->ip', 'like', "%{$data['ip']}%")
                    : $query),

            Filter::make('login')
                ->label(__('Login Identifier'))
                ->form([
                    TextInput::make('login')
                        ->label(__('Login Identifier'))
                        ->live(debounce: 500),
                ])
                ->query(fn (Builder $query, array $data): Builder => filled($data['login'] ?? null)
                    ? $query->where('properties->login', 'like', "%{$data['login']}%")
                    : $query),

            Filter::make('date_range')
                ->label(__('Date Range'))
                ->form([
                    DatePicker::make('from')
                        ->label(__('From Date'))
                        ->native(false)
                        ->displayFormat('Y-m-d'),

                    DatePicker::make('until')
                        ->label(__('Until Date'))
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->afterOrEqual('from'),
                ])
                ->columns(2)
                ->query(fn (Builder $query, array $data): Builder => $this->applyDateRange($query, $data))
                ->indicateUsing(fn (array $data): array => $this->dateRangeIndicators($data)),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('Export'))
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->action(fn () => app(ExcelServiceInterface::class)->download(
                    new AuthActivityLogExport($this->getTableQueryForExport()),
                    'login-activity-log-'.now()->format('Y-m-d-His').'.xlsx',
                    WriterType::XLSX
                ))
                ->visible(fn (): bool => auth()->user()->can('Activity Log View List')),

            $this->printPdfAction()
                ->visible(fn (): bool => auth()->user()->can('Activity Log View List')),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->modalHeading(__('Login Activity Log'))
                ->form(fn (Model $record): array => [
                    TextInput::make('description')
                        ->label(__('Description'))
                        ->default(__((string) $record->description))
                        ->disabled(),

                    KeyValue::make('context')
                        ->label(__('Request Details'))
                        ->default($this->requestProperties($record))
                        ->disabled(),
                ])
                ->modalSubmitAction(false),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function (Model $record): void {
                    $record->delete();
                    Toaster::success(__('Activity log deleted successfully'));
                })
                ->visible(fn (): bool => auth()->user()->can('Activity Log Delete')),
        ];
    }

    /**
     * Restricts the log to entries caused by a user holding any of the given
     * roles.
     *
     * Failed attempts against an unknown login have no causer at all, so they
     * drop out as soon as a role is chosen — a row with no user cannot belong
     * to a role.
     */
    protected function applyRoleFilter(Builder $query, array $roles): Builder
    {
        $roles = array_values(array_filter($roles));

        if ($roles === []) {
            return $query;
        }

        return $query->whereHasMorph(
            'causer',
            [User::class],
            fn (Builder $causer): Builder => $causer->whereHas(
                'roles',
                fn (Builder $causerRoles): Builder => $causerRoles->whereIn('name', $roles)
            )
        );
    }

    /**
     * Only roles that actually appear in the sign-in log, so the list stays
     * short and never offers a filter that returns nothing.
     *
     * @return array<string, string>
     */
    protected function roleOptions(): array
    {
        $causerIds = $this->activityQuery()
            ->where('causer_type', (new User)->getMorphClass())
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id');

        if ($causerIds->isEmpty()) {
            return [];
        }

        return Role::query()
            ->whereHas(
                'users',
                fn (Builder $users): Builder => $users->whereIn((new User)->getTable().'.id', $causerIds)
            )
            ->orderBy('name')
            ->pluck('name', 'name')
            ->map(fn (string $role): string => __($role))
            ->all();
    }

    /** Comma-separated role names for one log row's causer. */
    protected function causerRoles(Model $activity): string
    {
        $roles = $activity->causer?->roles;

        if (blank($roles)) {
            return '—';
        }

        return $roles->pluck('name')->map(fn (string $role): string => __($role))->implode('، ');
    }

    protected function eventLabel(?string $event): string
    {
        return match ($event) {
            AuthActivitySubscriber::EVENT_LOGIN => __('Login'),
            AuthActivitySubscriber::EVENT_LOGOUT => __('Logout'),
            AuthActivitySubscriber::EVENT_FAILED_LOGIN => __('Failed Login'),
            AuthActivitySubscriber::EVENT_LOCKOUT => __('Lockout'),
            AuthActivitySubscriber::EVENT_TOKEN_REFRESHED => __('Token Refreshed'),
            AuthActivitySubscriber::EVENT_PASSWORD_RESET => __('Password Reset'),
            AuthActivitySubscriber::EVENT_REGISTERED => __('Registered'),
            default => filled($event) ? __($event) : '—',
        };
    }

    /** @return array<string, string> */
    protected function authEventOptions(): array
    {
        return [
            AuthActivitySubscriber::EVENT_LOGIN => __('Login'),
            AuthActivitySubscriber::EVENT_LOGOUT => __('Logout'),
            AuthActivitySubscriber::EVENT_FAILED_LOGIN => __('Failed Login'),
            AuthActivitySubscriber::EVENT_LOCKOUT => __('Lockout'),
            AuthActivitySubscriber::EVENT_TOKEN_REFRESHED => __('Token Refreshed'),
            AuthActivitySubscriber::EVENT_PASSWORD_RESET => __('Password Reset'),
            AuthActivitySubscriber::EVENT_REGISTERED => __('Registered'),
        ];
    }

    protected function tableReportPdfTitle(): string
    {
        return __('Login Activity Log');
    }

    public function render()
    {
        return view('core::livewire.pages.activity-log.auth-log')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Activity Log'), 'url' => route('activity-logs.index')],
                ['title' => __('Login Activity Log'), 'url' => route('activity-logs.auth')],
            ],
        ]);
    }
}

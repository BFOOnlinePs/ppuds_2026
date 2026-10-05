<?php

namespace Modules\Core\Livewire\Pages\Notification;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\Core\Traits\ActivityLogReporting;
use Spatie\Permission\Models\Role;

/**
 * Every notification the app has sent, to every user — one row per
 * recipient, read from the `database` channel of GeneralNotification.
 */
class Index extends Component implements HasForms, HasTable
{
    use ActivityLogReporting;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => DatabaseNotification::query()
                ->where('notifiable_type', (new User)->getMorphClass())
                ->with('notifiable.roles'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                UserColumn::make('notifiable.name')
                    ->label(__('Recipient'))
                    ->user(fn (DatabaseNotification $record) => $record->notifiable)
                    ->withoutLink()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHasMorph(
                        'notifiable',
                        [User::class],
                        fn (Builder $notifiable): Builder => $notifiable
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                    )),

                TextColumn::make('notifiable_roles')
                    ->label(__('Roles'))
                    ->getStateUsing(fn (DatabaseNotification $record): string => $this->notifiableRoles($record))
                    ->badge()
                    ->separator('،')
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('title')
                    ->label(__('Title'))
                    ->getStateUsing(fn (DatabaseNotification $record): string => (string) data_get($record->data, 'title', '—'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('data->title', 'like', "%{$search}%"))
                    ->weight('bold'),

                TextColumn::make('message')
                    ->label(__('Message'))
                    ->getStateUsing(fn (DatabaseNotification $record): string => (string) data_get($record->data, 'message', '—'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('data->message', 'like', "%{$search}%"))
                    ->wrap()
                    ->limit(100),

                TextColumn::make('read_at')
                    ->label(__('Read Status'))
                    ->getStateUsing(fn (DatabaseNotification $record): string => $record->read_at ? __('Read') : __('Unread'))
                    ->badge()
                    ->color(fn (DatabaseNotification $record): string => $record->read_at ? 'success' : 'warning')
                    ->description(fn (DatabaseNotification $record): ?string => $record->read_at?->format('Y-m-d H:i'))
                    ->sortable(),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->defaultSort('created_at', 'desc');
    }

    protected function getTableFilters(): array
    {
        return [
            TernaryFilter::make('read_at')
                ->label(__('Read Status'))
                ->placeholder(__('All'))
                ->trueLabel(__('Read'))
                ->falseLabel(__('Unread'))
                ->nullable(),

            SelectFilter::make('notifiable_role')
                ->label(__('Roles'))
                ->options(fn (): array => Role::query()
                    ->orderBy('name')
                    ->pluck('name', 'name')
                    ->map(fn (string $role): string => __($role))
                    ->all())
                ->multiple()
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => filled($data['values'] ?? null)
                    ? $query->whereHasMorph(
                        'notifiable',
                        [User::class],
                        fn (Builder $notifiable): Builder => $notifiable->whereHas(
                            'roles',
                            fn (Builder $roles): Builder => $roles->whereIn('name', $data['values'])
                        )
                    )
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

    /** Comma-separated role names for one row's recipient. */
    protected function notifiableRoles(DatabaseNotification $notification): string
    {
        $roles = $notification->notifiable?->roles;

        if (blank($roles)) {
            return '—';
        }

        return $roles->pluck('name')->map(fn (string $role): string => __($role))->implode('، ');
    }

    public function render()
    {
        return view('core::livewire.pages.notification.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Notifications Log'), 'url' => route('notifications.index')],
            ],
        ]);
    }
}

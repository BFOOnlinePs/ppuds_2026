<?php

namespace Modules\Core\Livewire\Pages\Users;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Enums\FiltersLayout; // ✅ تم استيراد الـ Layout
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Throwable;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => User::query()->with('roles'))
            ->columns([
                UserColumn::make('name')
                    ->label(__('Name'))
                    ->user(fn (User $record) => $record)
                    ->withoutLink()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label(__('Roles'))
                    ->badge()
                    ->separator(',')
                    ->color(fn ($state) => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        default => 'primary',
                    })
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('Created At'))
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->label(__('Updated At'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters(
                $this->getTableFilters(),
                layout: FiltersLayout::AboveContent // ✅ هذا السطر يضع الفلتر فوق الجدول
            )
            ->filtersFormColumns(3) // ✅ تقسيم الفلاتر إلى 3 أعمدة لتظهر بجانب بعضها
            ->actions($this->getTableActions())
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add User'))
                    ->url(route('users.add'))
                    ->visible(fn() => auth()->user()->can('User Create'))
            ]);
    }

    protected function getTableFilters(): array
    {
        return [
            // المحذوفون مخفيون افتراضياً، ويظهرون عند طلبهم لاستعادتهم.
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

            // 1. فلتر الأدوار
            SelectFilter::make('roles')
                ->label(__('Roles'))
                ->relationship('roles', 'name')
                ->multiple()
                ->preload()
                ->searchable(),

            // 2. فلتر البحث عن الاسم والبريد
            Filter::make('user_details')
                ->label(__('Search Details'))
                ->form([
                    TextInput::make('name')->label(__('Name')),
                    TextInput::make('email')->label(__('Email')),
                    TextInput::make('phone')->label(__('Phone')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['name'],
                            fn (Builder $q) => $q->where('name', 'like', '%' . $data['name'] . '%')
                        )
                        ->when(
                            $data['email'],
                            fn (Builder $q) => $q->where('email', 'like', '%' . $data['email'] . '%')
                        )
                        ->when(
                            $data['phone'],
                            fn (Builder $q) => $q->where('phone', 'like', '%' . $data['phone'] . '%')
                        );
                })
                ->columns(3), // لجعل حقول البحث بجانب بعضها داخل الفلتر

            // 3. فلتر التاريخ
            Filter::make('created_at')
                ->label(__('Registration Date'))
                ->form([
                    DatePicker::make('created_from')->label(__('From Date')),
                    DatePicker::make('created_until')->label(__('To Date')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['created_from'],
                            fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date)
                        )
                        ->when(
                            $data['created_until'],
                            fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date)
                        );
                })
                ->columns(2), // لجعل حقلي التاريخ بجانب بعضهما
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->default($record->name)
                            ->disabled(),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->default($record->email)
                            ->disabled(),
                        TextInput::make('roles')
                            ->label(__('Roles'))
                            ->default($record->roles->pluck('name')->implode(', '))
                            ->disabled(),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('User View')),

            EditAction::make('edit')
                ->url(fn(User $record) => route('users.edit', $record->id))
                ->visible(fn() => auth()->user()->can('User Update')),

            DeleteAction::make('delete')
                ->action(fn (User $record) => $this->deleteUser($record))
                ->visible(fn (User $record) => auth()->user()->can('User Delete') && ! $record->trashed()),

            Action::make('restore')
                ->label(__('Restore'))
                ->icon('solar-history-bold-duotone')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('Restore User'))
                ->modalDescription(__('The user will regain access with the same roles and links they had before deletion.'))
                ->modalSubmitActionLabel(__('Restore'))
                ->action(fn (User $record) => $this->restoreUser($record))
                ->visible(fn (User $record) => auth()->user()->can('User Delete') && $record->trashed()),
        ];
    }

    protected function restoreUser(User $user): void
    {
        abort_unless(auth()->user()->can('User Delete'), 403);

        if (! $user->trashed()) {
            return;
        }

        try {
            $user->restore();

            Notification::make()
                ->title(__('User restored successfully.'))
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('Failed to restore the user. Please try again.'))
                ->danger()
                ->send();
        }
    }

    protected function deleteUser(User $user): void
    {
        abort_unless(auth()->user()->can('User Delete'), 403);

        if ((int) $user->id === (int) auth()->id()) {
            Notification::make()
                ->title(__('You cannot delete your own account.'))
                ->danger()
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($user): void {
                // الأدوار تبقى كما هي: الحذف ناعم، ومسحها هنا كان يعيد المستخدم
                // بلا صلاحيات عند الاستعادة. أما الجلسات فتُبطَل فوراً لمنع الدخول.
                $user->tokens()->delete();
                $user->delete();
            });

            Notification::make()
                ->title(__('User deleted successfully.'))
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('User could not be deleted.'))
                ->body(__('This user may be linked to records that must be removed first.'))
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('core::livewire.pages.users.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Users List'), 'url' => route('users.index')],
            ]
        ]);
    }
}

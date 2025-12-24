<?php

namespace Modules\PPUDS\Livewire\Pages\Student;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use GuzzleHttp\Promise\Create;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\StudnetProfile;
use View;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => StudnetProfile::query())
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('Created At')),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->label(__('Updated At')),
            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add User'))
                    ->url(route('users.add'))
                    ->visible(fn() => auth()->user()->can('User Create'))
            ])
            ->bulkActions([]);
    }

    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * The table filters.
     *
     * @return array
     */
    /*******  923c272c-8fa7-4881-8754-4bd584d02cc1  *******/
    protected function getTableFilters(): array
    {
        return [
            Filter::make('user_email')
            ->form([
                Forms\Components\TextInput::make('email')
                    ->label(__('Email')),
            ])
                ->query(function ($query, array $data) {
                    if (empty($data['email'])) {
                        return $query;
                    }

                    return $query->whereHas('user', function ($q) use ($data) {
                        $q->where('email', 'like', '%' . $data['email'] . '%');
                    });
                }),
            Filter::make('user_name')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Name'))
                ])
                ->query(function ($query, array $data) {
                    return $query->whereHas('user', function ($q) use ($data) {
                        $q->where('name', 'like', '%' . $data['name'] . '%');
                    });
                }),
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
                ->visible(fn() => auth()->user()->can('User Delete'))
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Students List'), 'url' => route('students.index')],
            ]
        ]);
    }
}

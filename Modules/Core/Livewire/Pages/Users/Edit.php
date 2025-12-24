<?php

namespace Modules\Core\Livewire\Pages\Users;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Hash;
use Livewire\Component;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Role;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public $user = null;

    public function mount($user)
    {
        $user = User::with('roles')->findOrFail($user);
        $this->user = $user;
        $this->data = $user->toArray();
        // dd($this->data);
        $this->data['roles'] = $user->roles->pluck('id')->toArray();
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)
                            ->schema([
                                Section::make(__('User Information'))
                                    ->columnSpan(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->required()
                                            ->label(__('Name')),
                                        TextInput::make('email')
                                            ->email()
                                            ->required()
                                            ->label(__('Email')),
                                        TextInput::make('password')
                                            ->label(__('Password'))
                                            ->password()
                                            ->required()
                                    ]),
                                Select::make('roles')
                                    ->multiple()
                                    ->preload()
                                    ->label(__('Roles'))
                                    ->default(fn() => $this->data['roles'] ?? [])
                                    ->options(fn() => Role::pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->placeholder(__('Select Roles')),

                                Section::make(__('Branches'))
                                    ->columnSpan(1)
                                    ->schema([
                                        Select::make('branch_id')
                                            ->preload()
                                            ->label(__('Branches'))
                                            ->options(fn() => Branch::get()->pluck('name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->placeholder(__('Select Branch'))
                                    ])
                                    ->visible(fn() => Module::isEnabled('branch')),                            ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        $state = $this->form->getState();

        $roles = $state['roles'] ?? [];
        unset($state['roles']);

        $this->user->update($state);

        $this->user->syncRoles($roles);
    }

    public function render()
    {
        return view('core::livewire.pages.users.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Users List'), 'url' => route('users.index')],
                ['title' => __('Edit User'), 'url' => route('users.edit', $this->data['id'])],
            ]
        ]);
    }
}

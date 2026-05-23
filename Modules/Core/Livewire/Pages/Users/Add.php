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
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Modules\Core\Entities\User;
use Spatie\Permission\Models\Role;

class Add extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();
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
                                    ->label(__('Name'))
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('email')
                                    ->label(__('Email'))
                                    ->email()
                                    ->required()
                                    ->unique('users', 'email')
                                    ->maxLength(255),

                                TextInput::make('phone')
                                    ->label(__('Phone'))
                                    ->tel()
                                    ->maxLength(255),

                                TextInput::make('password')
                                    ->label(__('Password'))
                                    ->password()
                                    ->required()
                                    ->minLength(8)
                                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
                            ]),

                        Section::make(__('Roles'))
                            ->columnSpan(1)
                            ->schema([
                                Select::make('roles')
                                    ->label(__('Roles'))
                                    ->multiple()
                                    ->preload()
                                    ->options(fn() => Role::pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->placeholder(__('Select Roles')),
                            ]),

                        // Section::make(__('Branches'))
                        //     ->columnSpan(1)
                        //     ->schema([
                        //         Select::make('branch_id')
                        //             ->label(__('Branches'))
                        //             ->preload()
                        //             ->options(fn() => Branch::get()->pluck('name', 'id'))
                        //             ->searchable()
                        //             ->placeholder(__('Select Branch'))
                        //     ])
                        //     ->visible(fn() => Module::isEnabled('branch')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        $data = $this->form->getState();

        $user = User::create($data);
        $user->generateAvatar();

        if (!empty($data['roles'])) {
            $roles = Role::whereIn('id', $data['roles'])->pluck('name')->toArray();
            $user->syncRoles($roles);
        }

        Notification::make()
            ->title(__('User saved successfully.'))
            ->success()
            ->send();

        return redirect()->route('users.index');
    }

    public function render()
    {
        return view('core::livewire.pages.users.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Users List'), 'url' => route('users.index')],
                ['title' => __('Add User'), 'url' => route('users.add')],
            ]
        ]);
    }
}

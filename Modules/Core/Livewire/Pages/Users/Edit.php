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
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Role;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public ?User $user = null;

    public function mount($user)
    {
        $this->user = $user;
        
        $this->data = $this->user->toArray();
        $this->data['roles'] = $this->user->roles->pluck('id')->toArray();
        
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
                                    ->label(__('Name'))
                                    ->required()
                                    ->maxLength(255),
                                    
                                TextInput::make('email')
                                    ->label(__('Email'))
                                    ->email()
                                    ->required()
                                    ->unique('users', 'email', ignorable: $this->user)
                                    ->maxLength(255),
                                    
                                TextInput::make('password')
                                    ->label(__('Password'))
                                    ->password()
                                    ->nullable()
                                    ->minLength(8)
                                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->helperText(__('Leave blank to keep the current password.')),
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

                        Section::make(__('Branches'))
                            ->columnSpan(1)
                            ->schema([
                                Select::make('branch_id')
                                    ->label(__('Branches'))
                                    ->preload()
                                    ->options(fn() => Branch::get()->pluck('name', 'id'))
                                    ->searchable()
                                    ->placeholder(__('Select Branch'))
                            ])
                            ->visible(fn() => Module::isEnabled('branch')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        $data = $this->form->getState();

        // استخراج الأدوار قبل تحديث المستخدم
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $this->user->update($data);

        // تحديث الأدوار بالاعتماد على الـ Names لتوافق Spatie Permissions
        if (!empty($roles)) {
            $roleNames = Role::whereIn('id', $roles)->pluck('name')->toArray();
            $this->user->syncRoles($roleNames);
        } else {
            $this->user->syncRoles([]);
        }

        Notification::make()
            ->title(__('User updated successfully.'))
            ->success()
            ->send();

        return redirect()->route('users.index');
    }

    public function render()
    {
        return view('core::livewire.pages.users.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Users List'), 'url' => route('users.index')],
                ['title' => __('Edit User'), 'url' => route('users.edit', $this->user->id)],
            ]
        ]);
    }
}
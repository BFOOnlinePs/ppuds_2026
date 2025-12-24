<?php

namespace Modules\PPUDS\Livewire\Pages\Student;

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
                Grid::make(4)
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
//                                Section::make(__('Branches'))
//                                    ->columnSpan(1)
//                                    ->schema([
//                                        Select::make('branch_id')
//                                            ->preload()
//                                            ->label(__('Branches'))
//                                            ->options(fn() => Branch::get()->pluck('name', 'id'))
//                                            ->searchable()
//                                            ->required()
//                                            ->placeholder(__('Select Branch'))
//                                    ])
//                                    ->visible(fn() => Module::isEnabled('branch')),
                            ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        // dd($this->data);
        $this->validate();
        $this->data['password'] = Hash::make($this->data['password']);

        $user = User::create($this->data);

        $user->generateAvatar();

        if (isset($this->data['roles']) && is_array($this->data['roles'])) {
            $roles = Role::whereIn('id', $this->data['roles'])->pluck('name')->toArray();
            $user->syncRoles($roles);
        }
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Students List'), 'url' => route('students.index')],
                ['title' => __('Add Student'), 'url' => route('students.add')],
            ]
        ]);
    }
}

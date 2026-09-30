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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Services\PpuApiService;
use Spatie\Permission\Models\Role;
use Throwable;

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

                                TextInput::make('phone')
                                    ->label(__('Phone'))
                                    ->tel()
                                    ->nullable()
                                    ->unique('users', 'phone', ignorable: $this->user)
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

        // استخراج الأدوار قبل تحديث المستخدم
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $this->user->update($data);
        $phoneChanged = $this->user->wasChanged('phone');

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

        if ($phoneChanged) {
            // الحالة الخام ما زالت تحمل كلمة المرور قبل التشفير
            $this->sendCompanySupervisorToUniversity(
                filled($this->data['password'] ?? null) ? (string) $this->data['password'] : null
            );
        }

        return redirect()->route('users.index');
    }

    /**
     * الهاتف هو اسم المستخدم وكلمة المرور الافتراضية لمشرف الشركة في نظام الجامعة،
     * فتغييره يستلزم إعادة إرساله لكل شركة يشرف فيها، كما تفعل شاشة مشرفي الشركات.
     */
    protected function sendCompanySupervisorToUniversity(?string $plainPassword): void
    {
        if (! $this->user->hasRole(UserRole::COMPANY_SUPERVISOR->value) || blank($this->user->phone)) {
            return;
        }

        $companies = Company::query()
            ->whereHas('branches.supervisors', fn (Builder $query) => $query->whereKey($this->user->id))
            ->with(['branches.supervisors', 'translations'])
            ->get();

        $apiService = app(PpuApiService::class);

        foreach ($companies as $company) {
            try {
                $result = $apiService->addCompanyToUniversity(
                    $company,
                    $plainPassword,
                    $this->user->id,
                    sendEvenIfCompanyExists: true,
                );
            } catch (Throwable $exception) {
                report($exception);

                $result = null;
            }

            [$title, $status] = match (true) {
                ($result['operation'] ?? null) === 'already_exists' => [__('Company supervisor already exists in university system'), 'warning'],
                $result === null || ($result['success'] ?? null) === false => [__('Unable to send company supervisor to university system'), 'danger'],
                default => [__('Company supervisor sent to university successfully'), 'success'],
            };

            Notification::make()
                ->title($title)
                ->body($company->name)
                ->status($status)
                ->send();
        }
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

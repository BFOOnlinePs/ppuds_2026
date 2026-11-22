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
use Modules\PPUDS\Entities\UserLoginPreference;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\OtpChannel;
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

        $preference = UserLoginPreference::query()->where('user_id', $this->user->id)->first();
        $this->data['login_mode'] = $preference?->login_mode?->value;
        $this->data['otp_channel'] = $preference?->otp_channel?->value;

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
                                    ->validationMessages([
                                        'unique' => __('This phone number is already taken'),
                                    ])
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

                        // How this person signs in — before any role rule or general setting.
                        Section::make(__('Sign-In Method'))
                            ->description(__('Leave as "General setting" to follow the role rules, then the general sign-in settings.'))
                            ->columnSpan(2)
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('login_mode')
                                        ->label(__('Sign-In Method'))
                                        ->options(InternalLoginMode::options())
                                        ->placeholder(__('General setting'))
                                        ->helperText(fn (callable $get): ?string => InternalLoginMode::tryFrom((int) $get('login_mode'))?->getDescription())
                                        ->live()
                                        ->native(false),

                                    Select::make('otp_channel')
                                        ->label(__('Send Codes By'))
                                        ->options(OtpChannel::options())
                                        ->placeholder(__('General setting'))
                                        ->helperText(__('SMS goes to the phone number above.'))
                                        ->native(false),
                                ]),
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

        // طريقة الدخول الخاصة بالمستخدم تُحفظ في جدولها، لا في جدول المستخدمين.
        $loginMode = InternalLoginMode::tryFrom((int) ($data['login_mode'] ?? 0));
        $otpChannel = OtpChannel::tryFrom((int) ($data['otp_channel'] ?? 0));
        unset($data['login_mode'], $data['otp_channel']);

        $this->user->update($data);
        $phoneChanged = $this->user->wasChanged('phone');

        // تحديث الأدوار بالاعتماد على الـ Names لتوافق Spatie Permissions
        if (!empty($roles)) {
            $roleNames = Role::whereIn('id', $roles)->pluck('name')->toArray();
            $this->user->syncRoles($roleNames);
        } else {
            $this->user->syncRoles([]);
        }

        $this->saveLoginPreference($loginMode, $otpChannel);

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
     * "General setting" on both fields removes the person's own method, so
     * they follow the role rules and general settings again.
     */
    protected function saveLoginPreference(?InternalLoginMode $loginMode, ?OtpChannel $otpChannel): void
    {
        if (! $loginMode && ! $otpChannel) {
            UserLoginPreference::query()->where('user_id', $this->user->id)->first()?->delete();

            return;
        }

        UserLoginPreference::query()->updateOrCreate(
            ['user_id' => $this->user->id],
            [
                'login_mode' => $loginMode,
                'otp_channel' => $otpChannel,
                'updated_by' => auth()->id(),
            ],
        );
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

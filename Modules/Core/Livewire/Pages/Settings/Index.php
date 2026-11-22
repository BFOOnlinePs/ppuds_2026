<?php

namespace Modules\Core\Livewire\Pages\Settings;

use App\View\Components\AppLayout;
use Carbon\Carbon;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;
use Modules\Core\Entities\Settings;
use Modules\Core\Enums\UserRole;
use Modules\Core\Settings\GeneralSettings;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\UserLoginPreference;
use Modules\PPUDS\Enums\GigEvaluationStatus;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Enums\OtpChannel;
use Modules\PPUDS\Enums\ReportStatus;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\WorkLocationEnforcement;
use Modules\PPUDS\Services\CompanyMembershipService;
use Modules\PPUDS\Settings\AuthenticationSettings;
use Modules\PPUDS\Settings\GeneralSettings as PPUDSGeneralSettings;
use Modules\PPUDS\Settings\KeycloakSettings;
use Modules\PPUDS\Settings\SmsSettings;
use Modules\PPUDS\Support\Sms\SmsManager;
use Spatie\Permission\Models\Role;

class Index extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public Settings $settingsModel;

    public function mount()
    {
        $this->settingsModel = Settings::firstOrCreate([]);

        // جلب الإعدادات من كلا الموديولين
        $generalSettings = app(GeneralSettings::class);
        $ppudsSettings = app(PPUDSGeneralSettings::class);
        $keycloakSettings = app(KeycloakSettings::class);

        $appVersions = DB::table('app_versions')->get()->map(function ($item) {
            return (array) $item;
        })->toArray();

        $this->form->fill([
            'site_name' => $generalSettings->site_name,
            'email_address_for_contact' => $generalSettings->email_address_for_contact,
            'site_description' => $generalSettings->site_description,

            'semester_type' => $ppudsSettings->semester_type->value,
            'year' => $ppudsSettings->year,
            'report_status' => $ppudsSettings->report_status->value,
            'login_method' => $ppudsSettings->login_method->value,
            'giz_evaluation_status' => $ppudsSettings->giz_evaluation_status->value,
            'start_semester' => $ppudsSettings->start_semester,
            'end_semester' => $ppudsSettings->end_semester,

            'evaluation_supervisor_max_grade' => $ppudsSettings->evaluation_supervisor_max_grade,
            'university_supervisor_max_grade' => $ppudsSettings->university_supervisor_max_grade,
            'company_max_grade' => $ppudsSettings->company_max_grade,

            'facebook_url' => $ppudsSettings->facebook_url,
            'linkedin_url' => $ppudsSettings->linkedin_url,
            'x_url' => $ppudsSettings->x_url,
            'instagram_url' => $ppudsSettings->instagram_url,

            'keycloak_base_url' => $keycloakSettings->base_url,
            'keycloak_realm' => $keycloakSettings->realm,
            'keycloak_client_id' => $keycloakSettings->client_id,
            'keycloak_client_secret' => $keycloakSettings->client_secret,
            'keycloak_redirect_uri' => $keycloakSettings->redirect_uri,
            'keycloak_mobile_client_id' => $keycloakSettings->mobile_client_id,
            'keycloak_mobile_client_secret' => $keycloakSettings->mobile_client_secret,
            'keycloak_api_client_id' => $keycloakSettings->api_client_id,
            'keycloak_realm_public_key' => $keycloakSettings->realm_public_key,
            'keycloak_allowed_resources' => $keycloakSettings->allowed_resources,
            'keycloak_password_grant_scope' => $keycloakSettings->password_grant_scope,

            'work_location_enforcement' => $ppudsSettings->work_location_enforcement->value,
            'work_location_allowed_distance_meters' => $ppudsSettings->work_location_allowed_distance_meters,
            'work_location_required_major_ids' => $ppudsSettings->work_location_required_major_ids,
            'work_location_enforce_on_check_out' => $ppudsSettings->work_location_enforce_on_check_out,

            'app_versions' => $appVersions,

            ...$this->authenticationFormState(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Settings')
                    ->tabs([
                        Tabs\Tab::make(__('General Site Info'))
                            ->icon('solar-global-bold-duotone')
                            ->schema([
                                Grid::make(3)->schema([
                                    Section::make()
                                        ->schema([
                                            TextInput::make('site_name')
                                                ->label(__('Site Name'))
                                                ->prefixIcon('solar-text-field-focus-bold-duotone')
                                                ->required(),
                                            TextInput::make('email_address_for_contact')
                                                ->label(__('Email Address For Contact'))
                                                ->prefixIcon('solar-letter-bold-duotone')
                                                ->email()
                                                ->required(),
                                            Textarea::make('site_description')
                                                ->label(__('Site Description'))
                                                ->rows(3)
                                                ->required(),

                                            TextInput::make('facebook_url')
                                                ->label(__('Facebook URL'))
                                                ->prefixIcon('fab-facebook')
                                                ->url(),
                                            TextInput::make('linkedin_url')
                                                ->label(__('LinkedIn URL'))
                                                ->prefixIcon('fab-linkedin')
                                                ->url(),
                                            TextInput::make('instagram_url')
                                                ->label(__('Instagram URL'))
                                                ->prefixIcon('fab-instagram')
                                                ->url(),
                                            TextInput::make('x_url')
                                                ->label(__('X URL'))
                                                ->prefixIcon('fab-x-twitter')
                                                ->url(),
                                        ])->columnSpan(2),

                                    Section::make()
                                        ->schema([
                                            SpatieMediaLibraryFileUpload::make('logo')
                                                ->model($this->settingsModel)
                                                ->collection('logo')
                                                ->label(__('Site Logo'))
                                                ->image()
                                                ->disk('media'),
                                        ])->columnSpan(1),
                                ]),
                            ]),

                        Tabs\Tab::make(__('Academic Settings'))

                            ->icon('solar-diploma-bold-duotone')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('semester_type')
                                        ->label(__('Semester Type'))
                                        ->prefixIcon('solar-calendar-bold-duotone')
                                        ->options(SemesterType::options())
                                        ->required(),

                                    TextInput::make('year')
                                        ->label(__('Academic Year'))
                                        ->prefixIcon('solar-calendar-search-bold-duotone')
                                        ->numeric()
                                        ->required(),

                                    DatePicker::make('start_semester')
                                        ->label(__('Start Semester'))
                                        ->prefixIcon('solar-calendar-search-bold-duotone')
                                        ->required(),

                                    DatePicker::make('end_semester')
                                        ->label(__('End Semester'))
                                        ->prefixIcon('solar-calendar-search-bold-duotone')
                                        ->required(),

                                    Select::make('report_status')
                                        ->label(__('Report Status'))
                                        ->prefixIcon('solar-document-bold-duotone')
                                        ->options(ReportStatus::options())
                                        ->required(),

                                    Select::make('login_method')
                                        ->label(__('Login Method'))
                                        ->prefixIcon('solar-key-bold-duotone')
                                        ->options(LoginMethod::options())
                                        ->required(),

                                    Select::make('giz_evaluation_status')
                                        ->label(__('GIZ Evaluation Status'))
                                        ->prefixIcon('solar-ranking-bold-duotone')
                                        ->options(GigEvaluationStatus::options())
                                        ->required(),
                                ]),

                                // توزيع العلامات: مجموع الحدود الثلاثة هو العلامة الكلية
                                // التي تظهر في شاشة علامات الطلاب.
                                Section::make(__('Grade Distribution'))
                                    ->description(__('The maximum grade each side can award. The student total is the sum of the three.'))
                                    ->icon('solar-ranking-bold-duotone')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('evaluation_supervisor_max_grade')
                                                ->label(__('Evaluation Supervisor Grade'))
                                                ->prefixIcon('solar-star-bold-duotone')
                                                ->numeric()
                                                ->minValue(0)
                                                ->maxValue(255)
                                                ->required(),

                                            TextInput::make('university_supervisor_max_grade')
                                                ->label(__('University Supervisor Grade'))
                                                ->prefixIcon('solar-user-speak-rounded-bold-duotone')
                                                ->numeric()
                                                ->minValue(0)
                                                ->maxValue(255)
                                                ->required(),

                                            TextInput::make('company_max_grade')
                                                ->label(__('Company Grade'))
                                                ->prefixIcon('solar-city-bold-duotone')
                                                ->numeric()
                                                ->minValue(0)
                                                ->maxValue(255)
                                                ->required(),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make(__('University Connection'))
                            ->icon('solar-key-bold-duotone')
                            ->schema([
                                // Split by audience: the realm itself, then one
                                // section per sign-in method. The web and mobile
                                // clients have separate ids and separate secrets,
                                // and mixing them up is the easiest way to break
                                // sign-in — so they never share a section.
                                Section::make(__('University Realm'))
                                    ->description(__('Shared by both sign-in methods. Leave a field empty to keep using the value from the .env file. The issuer and JWKS links are derived from the base URL and realm automatically.'))
                                    ->icon('solar-server-square-bold-duotone')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('keycloak_base_url')
                                                ->label(__('Base URL'))
                                                ->prefixIcon('solar-global-bold-duotone')
                                                ->url()
                                                ->placeholder('https://midad.ppu.edu'),

                                            TextInput::make('keycloak_realm')
                                                ->label(__('Realm'))
                                                ->prefixIcon('solar-shield-keyhole-bold-duotone')
                                                ->placeholder('PPU'),

                                            TextInput::make('keycloak_allowed_resources')
                                                ->label(__('Allowed Resources'))
                                                ->prefixIcon('solar-shield-check-bold-duotone')
                                                ->columnSpanFull()
                                                ->helperText(__('Comma separated realm clients. A token is rejected unless one of these appears in its resource_access, so every client that calls this API must be listed here — including the mobile app.')),

                                            Textarea::make('keycloak_realm_public_key')
                                                ->label(__('Realm Public Key (RS256)'))
                                                ->rows(4)
                                                ->autocomplete(false)
                                                ->columnSpanFull()
                                                ->helperText(__('Realm Settings → Keys → RS256 → Public key. Without it the system cannot verify university tokens and every authenticated request fails.')),
                                        ]),
                                    ]),

                                Section::make(__('Browser Sign In (Web)'))
                                    ->description(__('Used when signing in to this dashboard through the university.'))
                                    ->icon('solar-monitor-bold-duotone')
                                    ->collapsible()
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('keycloak_client_id')
                                                ->label(__('Web Client ID'))
                                                ->prefixIcon('solar-monitor-bold-duotone')
                                                ->placeholder('Dual-Studies-Laravel'),

                                            TextInput::make('keycloak_client_secret')
                                                ->label(__('Web Client Secret'))
                                                ->prefixIcon('solar-lock-password-bold-duotone')
                                                ->password()
                                                ->revealable()
                                                ->autocomplete(false)
                                                ->helperText(__('Leave empty for a public client.')),

                                            TextInput::make('keycloak_redirect_uri')
                                                ->label(__('Redirect URI'))
                                                ->prefixIcon('solar-arrow-right-bold-duotone')
                                                ->url()
                                                ->columnSpanFull()
                                                ->helperText(__('Must match a redirect URI registered on the web client in Keycloak.')),
                                        ]),
                                    ]),

                                Section::make(__('Mobile App Sign In'))
                                    ->description(__('Used when the phone app signs in through the university. The app has its own realm client with its own secret — these are not the web values above.'))
                                    ->icon('solar-smartphone-bold-duotone')
                                    ->collapsible()
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('keycloak_mobile_client_id')
                                                ->label(__('Mobile App Client ID'))
                                                ->prefixIcon('solar-smartphone-bold-duotone')
                                                ->placeholder('dualstudies-flutter-app'),

                                            TextInput::make('keycloak_mobile_client_secret')
                                                ->label(__('Mobile App Client Secret'))
                                                ->prefixIcon('solar-lock-password-bold-duotone')
                                                ->password()
                                                ->revealable()
                                                ->autocomplete(false)
                                                ->helperText(__('The mobile client has its own secret, separate from the web one.')),

                                            TextInput::make('keycloak_password_grant_scope')
                                                ->label(__('Mobile Login Scope'))
                                                ->prefixIcon('solar-list-bold-duotone')
                                                ->placeholder('openid profile offline_access')
                                                ->columnSpanFull()
                                                ->helperText(__('offline_access is required for the app to receive a refresh token.')),
                                        ]),
                                    ]),

                                Section::make(__('Advanced'))
                                    ->icon('solar-settings-bold-duotone')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        TextInput::make('keycloak_api_client_id')
                                            ->label(__('API Client ID (Audience)'))
                                            ->prefixIcon('solar-code-bold-duotone')
                                            ->helperText(__('Reserved. Nothing reads this value yet — access is controlled by Allowed Resources above.')),
                                    ]),
                            ]),

                        Tabs\Tab::make(__('Attendance Settings'))
                            ->icon('solar-map-point-wave-bold-duotone')
                            ->schema([
                                Section::make(__('Workplace Attendance'))
                                    ->description(__('Require students to be at the training branch when they record attendance. Branches with no coordinates on file are never blocked.'))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('work_location_enforcement')
                                                ->label(__('Workplace Check In Requirement'))
                                                ->prefixIcon('solar-map-point-bold-duotone')
                                                ->options(WorkLocationEnforcement::options())
                                                ->live()
                                                ->required(),

                                            TextInput::make('work_location_allowed_distance_meters')
                                                ->label(__('Allowed Range (meters)'))
                                                ->prefixIcon('solar-ruler-bold-duotone')
                                                ->numeric()
                                                ->minValue(10)
                                                ->maxValue(50000)
                                                ->required()
                                                ->visible(fn (callable $get): bool => $this->workLocationIsEnforced($get)),

                                            Select::make('work_location_required_major_ids')
                                                ->label(__('Majors Required To Check In From Workplace'))
                                                ->helperText(__('Any major not listed here is exempt from the workplace requirement.'))
                                                ->prefixIcon('solar-square-academic-cap-bold-duotone')
                                                ->options(fn (): array => Major::query()
                                                    ->with('translations')
                                                    ->get()
                                                    ->pluck('name', 'id')
                                                    ->toArray())
                                                ->multiple()
                                                ->searchable()
                                                ->preload()
                                                ->columnSpanFull()
                                                ->visible(fn (callable $get): bool => (int) $get('work_location_enforcement') === WorkLocationEnforcement::SELECTED_MAJORS->value),

                                            Toggle::make('work_location_enforce_on_check_out')
                                                ->label(__('Apply The Same Rule To Check Out'))
                                                ->columnSpanFull()
                                                ->visible(fn (callable $get): bool => $this->workLocationIsEnforced($get)),
                                        ]),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('App Versions'))
                            ->icon('solar-smartphone-linear')
                            ->schema([
                                Repeater::make('app_versions')
                                    ->label(__('Manage Platforms'))
                                    ->addActionLabel(__('Add Platform'))
                                    ->itemLabel(fn (array $state): ?string => $state['platform'] ?? null)
                                    ->deletable(false)
                                    ->schema([
                                        TextInput::make('platform')
                                            ->label(__('Platform (e.g., android, ios)'))
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('store_url')
                                            ->label(__('Store URL'))
                                            ->url()
                                            ->required()
                                            ->maxLength(255),

                                        Grid::make(2)->schema([
                                            TextInput::make('min_version')
                                                ->label(__('Min Version'))
                                                ->required()
                                                ->maxLength(255),

                                            TextInput::make('latest_version')
                                                ->label(__('Latest Version'))
                                                ->required()
                                                ->maxLength(255),
                                        ]),

                                        Toggle::make('maintenance_mode')
                                            ->label(__('Maintenance Mode'))
                                            ->default(false),

                                        Textarea::make('message')
                                            ->label(__('Maintenance / Update Message'))
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->defaultItems(0) // لا تضف عناصر فارغة افتراضياً
                                    ->collapsible(), // جعل العناصر قابلة للطي لتوفير المساحة
                            ]),

                        $this->authenticationTab(),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /** True while the workplace rule applies to anyone at all. */
    protected function workLocationIsEnforced(callable $get): bool
    {
        return (int) $get('work_location_enforcement') !== WorkLocationEnforcement::DISABLED->value;
    }

    /**
     * Sign-in code and company QR settings, prefixed `auth_` / `sms_` in the
     * form state. The SMS api key is deliberately left out: it never travels
     * to the browser, and an empty field on save keeps the stored key.
     */
    protected function authenticationFormState(): array
    {
        $auth = app(AuthenticationSettings::class);
        $sms = app(SmsSettings::class);

        return [
            'auth_web_login_mode' => $auth->web_login_mode->value,
            'auth_mobile_login_mode' => $auth->mobile_login_mode->value,
            'auth_role_login_rules' => $auth->role_login_rules,
            'auth_qr_login_enabled' => $auth->qr_login_enabled,
            'auth_personal_qr_login_enabled' => $auth->personal_qr_login_enabled,
            'auth_qr_login_roles' => $auth->qr_login_roles,
            'auth_qr_code_validity_days' => $auth->qr_code_validity_days,
            'auth_otp_length' => $auth->otp_length,
            'auth_otp_ttl_minutes' => $auth->otp_ttl_minutes,
            'auth_otp_max_attempts' => $auth->otp_max_attempts,
            'auth_otp_resend_cooldown_seconds' => $auth->otp_resend_cooldown_seconds,
            'auth_otp_max_sends_per_hour' => $auth->otp_max_sends_per_hour,
            'auth_otp_email_enabled' => $auth->otp_email_enabled,
            'auth_otp_sms_enabled' => $auth->otp_sms_enabled,
            'auth_otp_default_channel' => $auth->otp_default_channel->value,
            'auth_otp_allow_channel_choice' => $auth->otp_allow_channel_choice,
            'auth_otp_sms_fallback_to_email' => $auth->otp_sms_fallback_to_email,
            'auth_sms_daily_limit' => $auth->sms_daily_limit,

            'sms_driver' => $sms->driver,
            'sms_api_url' => $sms->api_url,
            'sms_sender_id' => $sms->sender_id,
            'sms_api_key' => null,
        ];
    }

    protected function authenticationTab(): Tabs\Tab
    {
        return Tabs\Tab::make(__('Sign In & Verification'))
            ->icon('solar-shield-keyhole-bold-duotone')
            ->schema([
                // Both features belong to the internal login only, so make it
                // obvious when the university sign-in is what is in use.
                Placeholder::make('auth_internal_login_only')
                    ->hiddenLabel()
                    ->content(__('These options apply to the internal login only. They have no effect while the login method is set to University SSO (PPU).'))
                    ->visible(fn (callable $get): bool => (int) $get('login_method') === LoginMethod::PPU->value),

                // Read top to bottom: the general method, exceptions by role,
                // QR codes, then the result — how each role will sign in.
                Section::make(__('Step 1 — General Sign-In Method'))
                    ->description(__('Applies to everyone who has no exception in step 2. Administrators and staff usually stay on the password.'))
                    ->icon('solar-password-minimalistic-input-bold-duotone')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('auth_web_login_mode')
                                ->label(__('Dashboard (Website) Sign-In'))
                                ->prefixIcon('solar-monitor-bold-duotone')
                                ->options(InternalLoginMode::options())
                                ->helperText(fn (callable $get): ?string => InternalLoginMode::tryFrom((int) $get('auth_web_login_mode'))?->getDescription())
                                ->native(false)
                                ->live()
                                ->required(),

                            Select::make('auth_mobile_login_mode')
                                ->label(__('Mobile App Sign-In'))
                                ->prefixIcon('solar-smartphone-bold-duotone')
                                ->options(InternalLoginMode::options())
                                ->helperText(fn (callable $get): ?string => InternalLoginMode::tryFrom((int) $get('auth_mobile_login_mode'))?->getDescription())
                                ->native(false)
                                ->live()
                                ->required(),
                        ]),
                    ]),

                Section::make(__('Step 2 — Exceptions By Role (optional)'))
                    ->description(__('Give a role a different method than step 1 — for example: students sign in with a code sent by SMS. Leave empty if everyone follows step 1. A single user can also get their own method from their page in Users.'))
                    ->icon('solar-users-group-rounded-bold-duotone')
                    ->schema([
                        Repeater::make('auth_role_login_rules')
                            ->hiddenLabel()
                            ->addActionLabel(__('Add Exception'))
                            ->reorderable()
                            ->defaultItems(0)
                            ->live()
                            ->itemLabel(fn (array $state): ?string => filled($state['role'] ?? null)
                                ? (UserRole::tryFrom($state['role'])?->getLabel() ?? __($state['role']))
                                : null)
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('role')
                                        ->label(__('Role'))
                                        ->options(fn (): array => $this->roleOptions())
                                        ->native(false)
                                        ->distinct()
                                        ->live()
                                        ->required(),

                                    Select::make('mode')
                                        ->label(__('Signs In With'))
                                        ->options(InternalLoginMode::options())
                                        ->placeholder(__('As in step 1'))
                                        ->native(false)
                                        ->live(),

                                    Select::make('channel')
                                        ->label(__('Send Codes By'))
                                        ->options(OtpChannel::options())
                                        ->placeholder(__('As in "Sending Codes"'))
                                        ->native(false)
                                        ->live(),
                                ]),
                            ]),
                    ]),

                Section::make(__('Step 3 — Sign In With A QR Code (Mobile App)'))
                    ->description(__('Two separate kinds of QR codes. Both are generated and printed from the company page, under "Sign-in QR Code".'))
                    ->icon('solar-qr-code-bold-duotone')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('auth_personal_qr_login_enabled')
                                ->label(__('Personal QR Code For Each Supervisor'))
                                ->helperText(__('Scanning it signs the person straight in — no email, no code. Hand it to them privately, like a password.'))
                                ->live(),

                            Toggle::make('auth_qr_login_enabled')
                                ->label(__('The Company\'s Printed QR Code'))
                                ->helperText(__('One poster for the whole company. After scanning, the person enters their email and the code sent to them.'))
                                ->live(),
                        ]),

                        CheckboxList::make('auth_qr_login_roles')
                            ->label(__('Who Can Sign In With QR Codes'))
                            ->helperText(__('Only roles that can be linked to a company can be chosen. Applies to both kinds of QR codes.'))
                            ->options(fn (): array => $this->roleOptions())
                            ->disableOptionWhen(fn (string $value): bool => ! in_array($value, CompanyMembershipService::supportedRoles(), true))
                            ->columns(2)
                            ->live()
                            ->required(fn (callable $get): bool => $this->anyQrLoginEnabled($get))
                            ->visible(fn (callable $get): bool => $this->anyQrLoginEnabled($get)),

                        TextInput::make('auth_qr_code_validity_days')
                            ->label(__('QR Code Validity (days)'))
                            ->helperText(__('Applies to newly generated codes. 0 means the code stays valid until it is revoked.'))
                            ->prefixIcon('solar-calendar-bold-duotone')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(3650)
                            ->required()
                            ->visible(fn (callable $get): bool => $this->anyQrLoginEnabled($get)),
                    ]),

                Section::make(__('Result — How Each Role Will Sign In'))
                    ->description(__('Updates as you change the options above. Nothing changes until you press Save.'))
                    ->icon('solar-checklist-minimalistic-bold-duotone')
                    ->schema([
                        Placeholder::make('auth_login_summary')
                            ->hiddenLabel()
                            ->content(fn (callable $get): HtmlString => $this->loginSummary($get)),
                    ]),

                Section::make(__('Code Rules'))
                    ->icon('solar-shield-check-bold-duotone')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('auth_otp_length')
                                ->label(__('Code Length (digits)'))
                                ->prefixIcon('solar-hashtag-bold-duotone')
                                ->numeric()->minValue(4)->maxValue(8)->required(),

                            TextInput::make('auth_otp_ttl_minutes')
                                ->label(__('Code Validity (minutes)'))
                                ->prefixIcon('solar-stopwatch-bold-duotone')
                                ->numeric()->minValue(1)->maxValue(60)->required(),

                            TextInput::make('auth_otp_max_attempts')
                                ->label(__('Wrong Attempts Allowed Per Code'))
                                ->prefixIcon('solar-danger-triangle-bold-duotone')
                                ->numeric()->minValue(1)->maxValue(10)->required(),

                            TextInput::make('auth_otp_resend_cooldown_seconds')
                                ->label(__('Wait Before Resending (seconds)'))
                                ->prefixIcon('solar-refresh-bold-duotone')
                                ->numeric()->minValue(30)->maxValue(600)->required(),

                            TextInput::make('auth_otp_max_sends_per_hour')
                                ->label(__('Codes Allowed Per Hour'))
                                ->prefixIcon('solar-clock-circle-bold-duotone')
                                ->numeric()->minValue(1)->maxValue(20)->required(),
                        ]),
                    ]),

                Section::make(__('Sending Codes'))
                    ->description(__('How the one-time code reaches the person — used by every method that sends a code.'))
                    ->icon('solar-letter-bold-duotone')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('auth_otp_email_enabled')
                                ->label(__('Send Codes By Email'))
                                ->live(),

                            Toggle::make('auth_otp_sms_enabled')
                                ->label(__('Send Codes By SMS'))
                                ->live(),

                            Select::make('auth_otp_default_channel')
                                ->label(__('Default Channel'))
                                ->prefixIcon('solar-plain-bold-duotone')
                                ->options(OtpChannel::options())
                                ->live()
                                ->required(),

                            Toggle::make('auth_otp_allow_channel_choice')
                                ->label(__('Let The User Choose The Channel'))
                                ->helperText(__('Only when both channels are enabled.')),

                            Toggle::make('auth_otp_sms_fallback_to_email')
                                ->label(__('Use Email When SMS Fails'))
                                ->visible(fn (callable $get): bool => (bool) $get('auth_otp_sms_enabled')),

                            TextInput::make('auth_sms_daily_limit')
                                ->label(__('Maximum Sign In Texts Per Day'))
                                ->helperText(__('Across the whole system, to protect the SMS balance.'))
                                ->prefixIcon('solar-chat-round-money-bold-duotone')
                                ->numeric()->minValue(1)->maxValue(100000)->required()
                                ->visible(fn (callable $get): bool => (bool) $get('auth_otp_sms_enabled')),
                        ]),
                    ]),

                Section::make(__('SMS Provider'))
                    ->icon('solar-smartphone-bold-duotone')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('sms_driver')
                                ->label(__('Provider'))
                                ->prefixIcon('solar-server-bold-duotone')
                                ->options(SmsManager::driverOptions())
                                ->required(),

                            TextInput::make('sms_sender_id')
                                ->label(__('Sender Name'))
                                ->prefixIcon('solar-user-id-bold-duotone')
                                ->maxLength(20),

                            TextInput::make('sms_api_url')
                                ->label(__('API URL'))
                                ->prefixIcon('solar-link-bold-duotone')
                                ->url()
                                ->maxLength(500),

                            TextInput::make('sms_api_key')
                                ->label(__('API Key'))
                                ->prefixIcon('solar-lock-password-bold-duotone')
                                ->password()
                                ->autocomplete('new-password')
                                ->maxLength(500)
                                ->dehydrated(fn (?string $state): bool => filled($state))
                                ->helperText(fn (): string => filled(app(SmsSettings::class)->api_key)
                                    ? __('A key is saved. Leave empty to keep it.')
                                    : __('No key saved yet.')),
                        ]),
                    ]),
            ]);
    }

    /**
     * The roles that actually exist, labelled the way the rest of the system
     * labels them.
     *
     * @return array<string, string>
     */
    protected function roleOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->mapWithKeys(fn (string $name): array => [
                $name => UserRole::tryFrom($name)?->getLabel() ?? __($name),
            ])
            ->all();
    }

    /** The company code or personal codes — the QR role list applies to both. */
    protected function anyQrLoginEnabled(callable $get): bool
    {
        return (bool) $get('auth_qr_login_enabled') || (bool) $get('auth_personal_qr_login_enabled');
    }

    /**
     * How each role will sign in with the options as they stand in the form
     * (not yet saved), following the same order LoginOtpService applies:
     * the first matching exception, then the general method. A method that
     * needs codes reads as the password while no channel is on.
     */
    protected function loginSummary(callable $get): HtmlString
    {
        $generalWeb = InternalLoginMode::tryFrom((int) $get('auth_web_login_mode')) ?? InternalLoginMode::PASSWORD;
        $generalMobile = InternalLoginMode::tryFrom((int) $get('auth_mobile_login_mode')) ?? InternalLoginMode::PASSWORD;

        $emailOn = (bool) $get('auth_otp_email_enabled');
        $smsOn = (bool) $get('auth_otp_sms_enabled');
        $codesPossible = $emailOn || $smsOn;

        $enabledChannels = array_values(array_filter([
            $emailOn ? OtpChannel::MAIL : null,
            $smsOn ? OtpChannel::SMS : null,
        ]));
        $defaultChannel = OtpChannel::tryFrom((int) $get('auth_otp_default_channel'));
        $defaultChannel = in_array($defaultChannel, $enabledChannels, true) ? $defaultChannel : ($enabledChannels[0] ?? null);

        $rules = collect($get('auth_role_login_rules') ?? [])->values();
        $qrRoles = (array) ($get('auth_qr_login_roles') ?? []);
        $personalOn = (bool) $get('auth_personal_qr_login_enabled');
        $companyOn = (bool) $get('auth_qr_login_enabled') && $codesPossible;

        $effective = fn (InternalLoginMode $mode): InternalLoginMode => $mode->usesCodes() && ! $codesPossible
            ? InternalLoginMode::PASSWORD
            : $mode;

        $rows = collect($this->roleOptions())->map(function (string $label, string $role) use ($rules, $generalWeb, $generalMobile, $effective, $enabledChannels, $defaultChannel, $qrRoles, $personalOn, $companyOn): array {
            $modeRule = $rules->first(fn ($rule) => ($rule['role'] ?? null) === $role && filled($rule['mode'] ?? null));
            $channelRule = $rules->first(fn ($rule) => ($rule['role'] ?? null) === $role && filled($rule['channel'] ?? null));

            $ruleMode = InternalLoginMode::tryFrom((int) ($modeRule['mode'] ?? 0));
            $web = $effective($ruleMode ?? $generalWeb);
            $mobile = $effective($ruleMode ?? $generalMobile);

            $ruleChannel = OtpChannel::tryFrom((int) ($channelRule['channel'] ?? 0));
            $channel = in_array($ruleChannel, $enabledChannels, true) ? $ruleChannel : $defaultChannel;

            return [
                'label' => $label,
                'fromRule' => $ruleMode !== null || $ruleChannel !== null,
                'web' => $web,
                'mobile' => $mobile,
                'channel' => ($web->usesCodes() || $mobile->usesCodes()) ? $channel : null,
                'personalQr' => $personalOn && in_array($role, $qrRoles, true),
                'companyQr' => $companyOn && in_array($role, $qrRoles, true),
            ];
        })->values();

        try {
            $ownMethodCount = UserLoginPreference::query()
                ->where(fn ($query) => $query->whereNotNull('login_mode')->orWhereNotNull('otp_channel'))
                ->count();
        } catch (\Throwable) {
            $ownMethodCount = 0;
        }

        return new HtmlString(view('core::livewire.pages.settings.partials.login-summary', [
            'rows' => $rows,
            'codesPossible' => $codesPossible,
            'ownMethodCount' => $ownMethodCount,
        ])->render());
    }

    /**
     * Rules that span several fields, which per-field validation cannot express.
     *
     * @throws ValidationException
     */
    protected function validateAuthenticationSettings(array $data): void
    {
        $roleRulesUseCodes = collect($data['auth_role_login_rules'] ?? [])
            ->contains(fn (array $rule): bool => (bool) InternalLoginMode::tryFrom((int) ($rule['mode'] ?? 0))?->usesCodes());

        $usesCodes = InternalLoginMode::from((int) $data['auth_web_login_mode'])->usesCodes()
            || InternalLoginMode::from((int) $data['auth_mobile_login_mode'])->usesCodes()
            || $roleRulesUseCodes
            || ! empty($data['auth_qr_login_enabled']);
        $emailEnabled = ! empty($data['auth_otp_email_enabled']);
        $smsEnabled = ! empty($data['auth_otp_sms_enabled']);

        if ($usesCodes && ! $emailEnabled && ! $smsEnabled) {
            throw ValidationException::withMessages([
                'data.auth_otp_email_enabled' => __('Enable at least one delivery channel, or the code cannot reach anyone.'),
            ]);
        }

        $defaultChannel = OtpChannel::tryFrom((int) ($data['auth_otp_default_channel'] ?? 0));

        if ($usesCodes && (
            ($defaultChannel === OtpChannel::MAIL && ! $emailEnabled) ||
            ($defaultChannel === OtpChannel::SMS && ! $smsEnabled)
        )) {
            throw ValidationException::withMessages([
                'data.auth_otp_default_channel' => __('The default channel must be one of the enabled channels.'),
            ]);
        }

        $unsupported = array_diff($data['auth_qr_login_roles'] ?? [], CompanyMembershipService::supportedRoles());

        if ($unsupported !== []) {
            throw ValidationException::withMessages([
                'data.auth_qr_login_roles' => __('Only roles that can be linked to a company are available.'),
            ]);
        }
    }

    protected function saveAuthenticationSettings(array $data): void
    {
        $auth = app(AuthenticationSettings::class);

        $auth->web_login_mode = InternalLoginMode::from((int) $data['auth_web_login_mode']);
        $auth->mobile_login_mode = InternalLoginMode::from((int) $data['auth_mobile_login_mode']);

        // In the order the administrator arranged them: the first match wins.
        $auth->role_login_rules = collect($data['auth_role_login_rules'] ?? [])
            ->filter(fn (array $rule): bool => filled($rule['role'] ?? null))
            ->map(fn (array $rule): array => [
                'role' => (string) $rule['role'],
                'mode' => InternalLoginMode::tryFrom((int) ($rule['mode'] ?? 0))?->value,
                'channel' => OtpChannel::tryFrom((int) ($rule['channel'] ?? 0))?->value,
            ])
            ->values()
            ->all();

        $auth->qr_login_enabled = (bool) ($data['auth_qr_login_enabled'] ?? false);
        $auth->personal_qr_login_enabled = (bool) ($data['auth_personal_qr_login_enabled'] ?? false);

        // The role list is hidden while QR sign-in is off; keep what was
        // stored so switching it back on restores the old choice.
        if (array_key_exists('auth_qr_login_roles', $data)) {
            $auth->qr_login_roles = array_values(array_map('strval', $data['auth_qr_login_roles'] ?? []));
        }

        $auth->qr_code_validity_days = (int) $data['auth_qr_code_validity_days'];
        $auth->otp_length = (int) $data['auth_otp_length'];
        $auth->otp_ttl_minutes = (int) $data['auth_otp_ttl_minutes'];
        $auth->otp_max_attempts = (int) $data['auth_otp_max_attempts'];
        $auth->otp_resend_cooldown_seconds = (int) $data['auth_otp_resend_cooldown_seconds'];
        $auth->otp_max_sends_per_hour = (int) $data['auth_otp_max_sends_per_hour'];
        $auth->otp_email_enabled = (bool) ($data['auth_otp_email_enabled'] ?? false);
        $auth->otp_sms_enabled = (bool) ($data['auth_otp_sms_enabled'] ?? false);
        $auth->otp_default_channel = OtpChannel::from((int) $data['auth_otp_default_channel']);
        $auth->otp_allow_channel_choice = (bool) ($data['auth_otp_allow_channel_choice'] ?? false);

        if (array_key_exists('auth_otp_sms_fallback_to_email', $data)) {
            $auth->otp_sms_fallback_to_email = (bool) $data['auth_otp_sms_fallback_to_email'];
        }

        if (array_key_exists('auth_sms_daily_limit', $data)) {
            $auth->sms_daily_limit = (int) $data['auth_sms_daily_limit'];
        }

        $auth->save();

        $sms = app(SmsSettings::class);
        $sms->driver = (string) $data['sms_driver'];
        $sms->api_url = trim((string) ($data['sms_api_url'] ?? ''));
        $sms->sender_id = trim((string) ($data['sms_sender_id'] ?? ''));

        // Write-only: an empty field means "keep the stored key".
        if (filled($data['sms_api_key'] ?? null)) {
            $sms->api_key = trim((string) $data['sms_api_key']);
        }

        $sms->save();
    }

    public function save()
    {
        $this->authorize('Setting Update');

        $data = $this->form->getState();

        // Before anything is written, so a rejected combination never leaves
        // the other tabs half saved.
        $this->validateAuthenticationSettings($data);

        $generalSettings = app(GeneralSettings::class);
        $generalSettings->site_name = $data['site_name'];
        $generalSettings->email_address_for_contact = $data['email_address_for_contact'];
        $generalSettings->site_description = $data['site_description'];
        $generalSettings->site_logo_url = $this->settingsModel->load('media')->getLogoUrl() ?: null;

        $generalSettings->save();

        $ppudsSettings = app(PPUDSGeneralSettings::class);
        $ppudsSettings->semester_type = SemesterType::from($data['semester_type']);
        $ppudsSettings->year = (int) $data['year'];
        $ppudsSettings->report_status = ReportStatus::from($data['report_status']);
        $ppudsSettings->login_method = LoginMethod::from($data['login_method']);
        $ppudsSettings->giz_evaluation_status = GigEvaluationStatus::from($data['giz_evaluation_status']);
        $ppudsSettings->start_semester = Carbon::parse($data['start_semester']);
        $ppudsSettings->end_semester = Carbon::parse($data['end_semester']);

        $ppudsSettings->evaluation_supervisor_max_grade = (int) $data['evaluation_supervisor_max_grade'];
        $ppudsSettings->university_supervisor_max_grade = (int) $data['university_supervisor_max_grade'];
        $ppudsSettings->company_max_grade = (int) $data['company_max_grade'];

        $ppudsSettings->facebook_url = $data['facebook_url'];
        $ppudsSettings->linkedin_url = $data['linkedin_url'];
        $ppudsSettings->x_url = $data['x_url'];
        $ppudsSettings->instagram_url = $data['instagram_url'];

        // The distance, major list and check-out toggle are hidden while
        // enforcement is off, so keep whatever was stored rather than wiping
        // it — turning the rule back on should restore the old setup.
        $enforcement = WorkLocationEnforcement::from((int) $data['work_location_enforcement']);
        $ppudsSettings->work_location_enforcement = $enforcement;

        if (array_key_exists('work_location_allowed_distance_meters', $data)) {
            $ppudsSettings->work_location_allowed_distance_meters = (int) $data['work_location_allowed_distance_meters'];
        }

        if ($enforcement === WorkLocationEnforcement::SELECTED_MAJORS) {
            $ppudsSettings->work_location_required_major_ids = collect($data['work_location_required_major_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (array_key_exists('work_location_enforce_on_check_out', $data)) {
            $ppudsSettings->work_location_enforce_on_check_out = (bool) $data['work_location_enforce_on_check_out'];
        }

        $ppudsSettings->save();

        $keycloakSettings = app(KeycloakSettings::class);

        foreach ([
            'base_url' => 'keycloak_base_url',
            'realm' => 'keycloak_realm',
            'client_id' => 'keycloak_client_id',
            'client_secret' => 'keycloak_client_secret',
            'redirect_uri' => 'keycloak_redirect_uri',
            'mobile_client_id' => 'keycloak_mobile_client_id',
            'mobile_client_secret' => 'keycloak_mobile_client_secret',
            'api_client_id' => 'keycloak_api_client_id',
            'realm_public_key' => 'keycloak_realm_public_key',
            'allowed_resources' => 'keycloak_allowed_resources',
            'password_grant_scope' => 'keycloak_password_grant_scope',
        ] as $property => $field) {
            $keycloakSettings->{$property} = trim((string) ($data[$field] ?? ''));
        }

        $keycloakSettings->save();

        $this->saveAuthenticationSettings($data);

        if (isset($data['app_versions'])) {
            $platformsToKeep = [];

            foreach ($data['app_versions'] as $versionData) {
                DB::table('app_versions')->updateOrInsert(
                    ['platform' => $versionData['platform']], // عمود فريد لتحديد التحديث أو الإضافة
                    [
                        'min_version' => $versionData['min_version'],
                        'latest_version' => $versionData['latest_version'],
                        'store_url' => $versionData['store_url'],
                        'maintenance_mode' => $versionData['maintenance_mode'] ?? 0,
                        'message' => $versionData['message'] ?? null,
                        'updated_at' => now(),
                    ]
                );

                $platformsToKeep[] = $versionData['platform'];
            }

            // (اختياري) حذف المنصات التي قام المستخدم بمسحها من الـ Repeater
            DB::table('app_versions')
                ->whereNotIn('platform', $platformsToKeep)
                ->delete();

            // مسح كاش إعدادات التطبيق حتى يظهر الإصدار الجديد للطلاب فوراً (ConfigController)
            Cache::forget('app_bootstrap_config');
        }

        //        auth()->user()->notify(new GeneralNotification(
        //            __('Settings Updated'),
        //            __('System settings have been updated successfully'),
        //            route('settings'),
        //            icon: 'welcome',
        //            color: 'success',
        //        ));

        //        event(new RefreshNotificationEvent());

        LivewireAlert::title(__('Saved Successfully'))->success()->toast()->show();
    }

    public function render()
    {
        return view('core::livewire.pages.settings.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Settings'), 'url' => route('settings')],
            ],
        ]);
    }
}

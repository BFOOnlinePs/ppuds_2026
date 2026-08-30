<?php

namespace Modules\Core\Livewire\Pages\Settings;

use App\View\Components\AppLayout;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
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
use Illuminate\Support\Facades\DB;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;
use Modules\Core\Entities\Settings;
use Modules\Core\Settings\GeneralSettings;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Enums\GigEvaluationStatus;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Enums\ReportStatus;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\WorkLocationEnforcement;
use Modules\PPUDS\Settings\GeneralSettings as PPUDSGeneralSettings;
use Modules\PPUDS\Settings\KeycloakSettings;

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
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /** True while the workplace rule applies to anyone at all. */
    protected function workLocationIsEnforced(callable $get): bool
    {
        return (int) $get('work_location_enforcement') !== WorkLocationEnforcement::DISABLED->value;
    }

    public function save()
    {
        $this->authorize('Setting Update');

        $data = $this->form->getState();

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

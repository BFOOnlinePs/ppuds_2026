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
use Modules\PPUDS\Enums\GigEvaluationStatus;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Enums\ReportStatus;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Settings\GeneralSettings as PPUDSGeneralSettings;

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

        $ppudsSettings->save();

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

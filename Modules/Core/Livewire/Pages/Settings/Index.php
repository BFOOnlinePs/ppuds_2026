<?php

namespace Modules\Core\Livewire\Pages\Settings;

use App\Models\User;
use App\View\Components\AppLayout;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Jantinnerezo\LivewireAlert\Enums\Position;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Core\Entities\Settings;
use Modules\Core\Events\AbstractNotificationEvent;
use Modules\Core\Events\RefreshNotificationEvent;
use Modules\Core\Events\TestEvent;
use Modules\Core\Filament\Forms\Components\FileUploadWithPreview;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Core\Notifications\TestNotification;
use Modules\Core\Settings\GeneralSettings;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Modules\Items\Events\ProductUpdated;

class Index extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data;
    public Settings $settingsModel;

    public function mount()
    {
        $this->settingsModel = Settings::firstOrCreate([]);
        $generalSettings = app(GeneralSettings::class);
        $this->data = [
            'site_name' => $generalSettings->site_name,
            'email_address_for_contact' => $generalSettings->email_address_for_contact,
            'site_description' => $generalSettings->site_description,
            'site_logo_url' => $generalSettings->site_logo_url,
        ];
        $this->form->fill($this->data);
    }

    public function triggerEvent(): void
    {
        TestEvent::dispatch('Hello Mohamad Maraqa');
    }

    #[On('echo:product_updated,.ProductUpdated')]
    public function handleNotification(): void
    {
        LivewireAlert::title('تم ربط ال Reverb بنجاح')
            ->success()
            ->toast()
            ->position('bottom-start')
            ->show();
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->settingsModel)
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make(__('Site Information'))
                            ->schema([
                                TextInput::make('site_name')
                                    ->label(__('Site Name'))
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('email_address_for_contact')
                                    ->label(__('Email Address For Contact'))
                                    ->email()
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('site_description')
                                    ->label(__('Site Description'))
                                    ->required()
                                    ->maxLength(500),
                            ])
                            ->columnSpan(2),

                        Section::make()
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('logo')
                                    ->model($this->settingsModel)
                                    ->collection('logo')
                                    ->label(__('Site Logo'))
                                    ->disk('media')
                                    ->image()
                                    ->multiple(false)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(1),
                    ]),
            ])
            ->statePath('data');
    }

    protected function Rule(): array
    {
        return [
            'data.site_name' => 'required',
            'data.email_address_for_contact' => 'required|email',
            'data.site_description' => 'required',
        ];
    }

    protected function messages(): array
    {
        return [
            'data.site_name.required' => __('Site Name is required'),
            'data.email_address_for_contact.required' => __('Email Address For Contact is required'),
            'data.email_address_for_contact.email' => __('Email Address For Contact must be a valid email address'),
            'data.site_description.required' => __('Site Description is required'),
        ];
    }

    public function save()
    {
        $validatedData = $this->form->validate();

        $generalSettings = app(GeneralSettings::class);
        $generalSettings->site_name = $validatedData['data']['site_name'];
        $generalSettings->email_address_for_contact = $validatedData['data']['email_address_for_contact'];
        $generalSettings->site_description = $validatedData['data']['site_description'];
        $generalSettings->site_logo_url = 'logo';
        $this->settingsModel->handleLogoUpload($this->data['logo'] ?? null);

        $generalSettings->save();

//            auth()->user()->notify(new GeneralNotification(
//                'اعداد جديد',
//                'تم تحديث الاعدادات بنجاح',
//                route('settings'),
//                icon: 'welcome',color: 'success',notifiable: auth()->user()
//            ));

        auth()->user()->notify(new GeneralNotification(
            'اعداد جديد',
            'تم تحديث الاعدادات بنجاح',
            route('settings'),
            icon: 'welcome',
            color: 'success',
        ));

        event(new RefreshNotificationEvent());

//        auth()->user()->notify(new TestNotification());
    }

    public function render()
    {
        return view('core::livewire.pages.settings.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Settings'), 'url' => route('settings')],
            ]
        ]);
    }
}

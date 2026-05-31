<?php

namespace Modules\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Core\Entities\Settings;
use Modules\Core\Settings\GeneralSettings;
use Throwable;

class HeaderNotification extends Component
{
    #[On('refresh-notification-bell')]
    public function refreshNotifications()
    {
        $this->dispatch('$refresh');

    }

    public function render()
    {
        $user = Auth::user();
        $notifications = $user ? $user->notifications()->latest()->take(10)->get() : collect();

        $unreadCount = $user ? $user->unreadNotifications()->count() : 0;

        return view('core::livewire.header-notification', [
            'notifications'            => $notifications,
            'unreadCount'              => $unreadCount,
            'defaultNotificationImage' => $this->defaultNotificationImage(),
        ]);
    }

    private function defaultNotificationImage(): string
    {
        try {
            $settingsLogo = app(GeneralSettings::class)->site_logo_url;

            if (filled($settingsLogo)) {
                return $settingsLogo;
            }

            $mediaLogo = Settings::query()->first()?->getLogoUrl();

            if (filled($mediaLogo)) {
                return $mediaLogo;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return asset('assets/images/user-profile.jpeg');
    }
}

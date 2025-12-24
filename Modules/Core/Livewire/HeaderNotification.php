<?php

namespace Modules\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;

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
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
        ]);
    }
}

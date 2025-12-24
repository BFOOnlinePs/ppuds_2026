<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Modules\Core\Settings\GeneralSettings;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */

     public $settings;

    public function __construct()
    {
        $this->settings = app(GeneralSettings::class);
    }

    public function render(): View
    {
//        $user = Auth::user();
//        $notifications = $user ? $user->notifications()->latest()->take(20)->get() : collect();
//
//        $unreadCount = $user ? $user->unreadNotifications()->count() : 0;

        return view('layouts.app' , [
            'settings'      => $this->settings,
        ]);
    }
}

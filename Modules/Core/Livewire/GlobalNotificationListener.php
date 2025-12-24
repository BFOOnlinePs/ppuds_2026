<?php

namespace Modules\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\On;
use Livewire\Component;

class GlobalNotificationListener extends Component
{
    public function getListeners()
    {
        return [
            "echo:refresh_notification,.RefreshNotificationEvent" => 'handleNotification',
        ];
    }

    public function handleNotification($notification): void
    {
        LivewireAlert::title('ddd')
            ->text('test')
            ->success()
            ->toast()
            ->position('bottom-start')
            ->show();

        $this->dispatch('refresh-notification-bell');
    }

    public function render()
    {
        return <<<'HTML'
        <div></div>
        HTML;
    }
}

<?php

namespace App\Providers\Wirechat;

use Wirechat\Wirechat\Panel;
use Wirechat\Wirechat\PanelProvider;

class ChatsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
             ->id('chats')
             ->path('admin/chats')
             ->middleware(['web', 'auth'])
             ->default();
    }
}

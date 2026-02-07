<?php

namespace Modules\Core\Providers;

use Wirechat\Wirechat\Panel;
use Wirechat\Wirechat\PanelProvider;

class ChatsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
             ->id('chats')
             ->path('chats')
             ->middleware(['web'])
             ->default();
    }
}

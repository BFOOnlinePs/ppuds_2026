<?php

namespace Modules\Core\Providers;

use Wirechat\Wirechat\Panel;
use Wirechat\Wirechat\PanelProvider;
use Wirechat\Wirechat\Support\Color;


class ChatsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
             ->id('chats')
             ->path('admin/chat-messages')
             ->middleware(['web', 'auth'])
             ->colors([
                'primary' => Color::Orange,
                'danger' => Color::Rose,
                'gray' => Color::Gray,
                'info' => Color::Blue,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
             ->default();
    }
}

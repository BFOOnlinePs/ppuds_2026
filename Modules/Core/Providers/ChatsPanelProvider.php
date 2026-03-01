<?php

namespace Modules\Core\Providers;

use Modules\Core\Entities\User;
use Wirechat\Wirechat\Panel;
use Wirechat\Wirechat\PanelProvider;
use Wirechat\Wirechat\Support\Color;
use Wirechat\Wirechat\Http\Resources\WireChatUserResource;


class ChatsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
             ->id('chats')
             ->path('admin/chat-messages')
             ->middleware(['web', 'sanctum', 'auth'])
             ->guards(['sanctum', 'web'])
             ->colors([
                'primary' => Color::Orange,
                'danger' => Color::Rose,
                'gray' => Color::Gray,
                'info' => Color::Blue,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            // ->createGroupAction()
            // ->createChatAction()
            // ->searchUsersUsing(function (string $needle) {
            //     return WireChatUserResource::collection(
            //         User::query()
            //             ->where('name', 'like', "%{$needle}%")
            //             ->limit(20)
            //             ->get()
            //     );
            // })
             ->default();
    }
}

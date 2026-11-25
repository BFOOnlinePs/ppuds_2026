<?php

namespace App\Livewire\Wirechat;

use Modules\Core\Entities\User;
use Modules\PPUDS\Services\ChatContactService;
use Wirechat\Wirechat\Livewire\New\Chat;

/**
 * The wirechat "new chat" modal applies the same contact rules as the
 * application's own chat page (ChatContactService), instead of letting any
 * user open a conversation with anyone.
 */
class NewChat extends Chat
{
    public function createConversation($id, string $class)
    {
        $receiver = User::find($id);

        abort_unless(
            $receiver && app(ChatContactService::class)->canStartConversation(auth()->user(), $receiver),
            403
        );

        return parent::createConversation($receiver->id, User::class);
    }
}

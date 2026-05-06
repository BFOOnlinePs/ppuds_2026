<?php

namespace Modules\PPUDS\Livewire\Pages\ChatMessage;

use Modules\PPUDS\Services\PpudsNotificationService;
use Wirechat\Wirechat\Livewire\Chat\Chat as BaseChat;
use Wirechat\Wirechat\Models\Message;

class Show extends BaseChat
{
    protected function dispatchMessageCreatedEvent(Message $message): void
    {
        parent::dispatchMessageCreatedEvent($message);

        app(PpudsNotificationService::class)->chatMessageCreated($message);
    }
}

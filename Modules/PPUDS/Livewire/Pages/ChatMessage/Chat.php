<?php

namespace Modules\PPUDS\Livewire\Pages\ChatMessage;

use Livewire\Component;
use Wirechat\Wirechat\Livewire\Chats\Chats as BaseChats;

class Chat extends BaseChats
{
    public function test()
    {
        dd('Custom behavior activated!');
    }
}
<?php

namespace App\Livewire\Wirechat;

use Modules\Core\Entities\User;
use Modules\PPUDS\Services\ChatContactService;
use Wirechat\Wirechat\Livewire\New\Group;

/**
 * The wirechat "new group" modal only accepts members the current user is
 * allowed to contact (ChatContactService), matching the application's own
 * chat page.
 */
class NewGroup extends Group
{
    public function addMember($id, string $class)
    {
        $this->ensureCanContact($id);

        return parent::addMember($id, User::class);
    }

    public function toggleMember($id, string $class)
    {
        $this->ensureCanContact($id);

        return parent::toggleMember($id, User::class);
    }

    public function create()
    {
        foreach ($this->selectedMembers as $member) {
            $this->ensureCanContact($member->id);
        }

        return parent::create();
    }

    private function ensureCanContact($id): void
    {
        $receiver = User::find($id);

        abort_unless(
            $receiver && app(ChatContactService::class)->canStartConversation(auth()->user(), $receiver),
            403
        );
    }
}

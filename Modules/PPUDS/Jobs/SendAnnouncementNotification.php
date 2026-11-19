<?php

namespace Modules\PPUDS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\PPUDS\Entities\Announcement;
use Modules\PPUDS\Services\PpudsNotificationService;

/**
 * Sends an announcement's notification to everyone it targets, off the
 * request, since a whole role can be thousands of users.
 */
class SendAnnouncementNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** A retry would notify again the users already reached. */
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $announcementId)
    {
        $this->afterCommit();
    }

    public function handle(PpudsNotificationService $notifications): void
    {
        $announcement = Announcement::query()
            ->with(['media', 'translations'])
            ->find($this->announcementId);

        if ($announcement) {
            $notifications->sendAnnouncementNotification($announcement);
        }
    }
}

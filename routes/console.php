<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\PPUDS\Entities\LoginOtp;
use Modules\PPUDS\Services\PpudsNotificationService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sign-in codes live minutes; their rows are removed the next day.
Schedule::command('model:prune', ['--model' => [LoginOtp::class]])->daily();

// Scheduled announcements notify their users once their publish time arrives.
Schedule::call(fn () => app(PpudsNotificationService::class)->dispatchDueAnnouncementNotifications())
    ->name('ppuds:announcement-notifications')
    ->everyMinute()
    ->withoutOverlapping();

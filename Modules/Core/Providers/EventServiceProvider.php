<?php

namespace Modules\Core\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Core\Events\AbstractNotificationEvent;
use Modules\Core\Listeners\StoreAbstractNotificationListener;
use Modules\Core\Livewire\HeaderNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        AbstractNotificationEvent::class => [
            StoreAbstractNotificationListener::class
        ]
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}

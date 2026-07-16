<?php

namespace Modules\Core\Notifications;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Entities\Settings;
use Modules\Core\Settings\GeneralSettings;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;
use Throwable;

class GeneralNotification extends Notification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $title;
    public string $message;
    public string $url;
    public string $icon;
    public string $color;
    public ?string $image;

    public function __construct(
        string $title,
        string $message,
        string $url = '#',
        string $icon = 'bell',
        string $color = 'text-primary',
        ?string $image = null,
    ) {
        $this->title = $title;
        $this->message = $message;
        $this->url = $url;
        $this->icon = $icon;
        $this->color = $color;
        $this->image = $image ?: $this->defaultImage();
    }

    public function via($notifiable): array
    {

        return ['broadcast', 'database' , FcmChannel::class];
    }

    public function toArray($notifiable): array
    {
        return [
            'title'   => $this->title,
            'message' => $this->message,
            'url'     => $this->url,
            'icon'    => $this->icon,
            'color'   => $this->color,
            'image'   => $this->image,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title'     => $this->title,
            'message'   => $this->message,
            'url'       => $this->url,
            'icon'      => $this->icon,
            'color'     => $this->color,
            'image'     => $this->image,
            'sent_at'   => now()->toIso8601String(),
        ]);
    }

    public function toFcm($notifiable): FcmMessage
    {
        return (new FcmMessage(notification: new FcmNotification(
            title: $this->title,
            body: $this->message,
            image: $this->image
        )))
            ->data([
                'title' => $this->title,
                'message' => $this->message,
                'url' => $this->url,
                'icon' => $this->icon,
                'color' => $this->color,
                'image' => (string) $this->image,
            ])
            ->custom([
                'android' => [
                    'notification' => [
                        'color' => '#0A0A0A',
                    ],
                    'fcm_options' => [
                        'analytics_label' => 'analytics',
                    ],
                ],
                'apns' => [
                    'fcm_options' => [
                        'analytics_label' => 'analytics',
                    ],
                ],
            ]);
    }

    public function broadcastAs(): string
    {
        return 'BroadcastNotificationCreated';
    }

    private function defaultImage(): ?string
    {
        try {
            $settingsLogo = app(GeneralSettings::class)->site_logo_url;

            if (filled($settingsLogo)) {
                return $settingsLogo;
            }

            $mediaLogo = Settings::query()->first()?->getLogoUrl();

            if (filled($mediaLogo)) {
                return $mediaLogo;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return asset('assets/images/user-profile.jpeg');
    }
}

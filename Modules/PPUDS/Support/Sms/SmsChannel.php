<?php

namespace Modules\PPUDS\Support\Sms;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Laravel notification channel for texts: `via()` returns this class and the
 * notification provides `toSms($notifiable): string`.
 *
 * It only knows the SmsManager, never a specific provider. When the text
 * cannot be sent and the notification offers an `emailFallback()`, that copy
 * is sent straight away instead of failing.
 */
class SmsChannel
{
    public function __construct(private readonly SmsManager $sms) {}

    /**
     * @throws SmsDeliveryException when the text failed and there is no fallback.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        try {
            $this->deliver($notifiable, $notification);
        } catch (SmsDeliveryException $e) {
            $fallback = method_exists($notification, 'emailFallback') ? $notification->emailFallback() : null;

            if (! $fallback || ! method_exists($notifiable, 'notifyNow')) {
                throw $e;
            }

            Log::warning('SMS delivery failed, sending the email fallback instead.', [
                'notification' => $notification::class,
                'notifiable_id' => $notifiable->id ?? null,
                'reason' => $e->getMessage(),
            ]);

            $notifiable->notifyNow($fallback);
        }
    }

    private function deliver(object $notifiable, Notification $notification): void
    {
        $to = trim((string) ($notifiable->routeNotificationFor('sms', $notification)
            ?: ($notifiable->phone ?? '')));

        if ($to === '') {
            throw new SmsDeliveryException('The recipient has no phone number on file.');
        }

        $this->sms->driver()->send($to, (string) $notification->toSms($notifiable));
    }
}

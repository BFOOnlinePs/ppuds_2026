<?php

namespace Modules\PPUDS\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\PPUDS\Enums\OtpChannel;
use Modules\PPUDS\Support\Sms\SmsChannel;

/**
 * Delivers a sign-in code by email or SMS.
 *
 * Queued so that asking for a code takes the same time whether or not the
 * email belongs to anyone, and encrypted on the queue so the code is never
 * readable in the jobs table. When the text cannot be sent and the settings
 * allow it, the same code is sent by email instead.
 */
class LoginOtpNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 5;

    public function __construct(
        private readonly string $code,
        private readonly OtpChannel $channel,
        private readonly int $ttlMinutes,
        private readonly bool $fallbackToEmail = false,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [$this->channel === OtpChannel::SMS ? SmsChannel::class : 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your sign-in code'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? '']))
            ->line(__('Use this code to sign in to :app:', ['app' => config('app.name')]))
            ->line('**'.$this->code.'**')
            ->line(__('The code expires in :minutes minutes and can be used once.', ['minutes' => $this->ttlMinutes]))
            ->line(__('If you did not try to sign in, you can ignore this email. Never share this code with anyone.'));
    }

    public function toSms(object $notifiable): string
    {
        return __(':code is your :app sign-in code. It expires in :minutes minutes. Do not share it.', [
            'code' => $this->code,
            'app' => config('app.name'),
            'minutes' => $this->ttlMinutes,
        ]);
    }

    /**
     * The email copy SmsChannel sends when this text could not be delivered,
     * or null when there is nothing to fall back to.
     */
    public function emailFallback(): ?self
    {
        if ($this->channel !== OtpChannel::SMS || ! $this->fallbackToEmail) {
            return null;
        }

        return (new self($this->code, OtpChannel::MAIL, $this->ttlMinutes))->locale($this->locale);
    }
}

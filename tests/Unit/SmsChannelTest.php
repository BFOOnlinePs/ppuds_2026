<?php

namespace Tests\Unit;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Modules\PPUDS\Enums\OtpChannel;
use Modules\PPUDS\Interfaces\SmsGatewayInterface;
use Modules\PPUDS\Notifications\LoginOtpNotification;
use Modules\PPUDS\Settings\SmsSettings;
use Modules\PPUDS\Support\Sms\SmsChannel;
use Modules\PPUDS\Support\Sms\SmsDeliveryException;
use Modules\PPUDS\Support\Sms\SmsManager;
use Tests\TestCase;

class SmsChannelTest extends TestCase
{
    /** @var array<int, array{to: string, message: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        SmsSettings::fake(['driver' => 'test', 'api_url' => '', 'api_key' => '', 'sender_id' => ''], false);
    }

    private function useGateway(bool $fails): void
    {
        $sent = &$this->sent;

        app(SmsManager::class)->extend('test', function () use (&$sent, $fails) {
            return new class($sent, $fails) implements SmsGatewayInterface
            {
                public function __construct(private array &$sent, private bool $fails) {}

                public function send(string $to, string $message): void
                {
                    if ($this->fails) {
                        throw new SmsDeliveryException('Gateway rejected the message.');
                    }

                    $this->sent[] = compact('to', 'message');
                }
            };
        });
    }

    private function recipient(?string $phone = '0599123456'): AnonymousNotifiable
    {
        return (new AnonymousNotifiable)
            ->route('sms', $phone)
            ->route('mail', 'person@example.com');
    }

    public function test_the_code_is_texted_through_the_configured_driver(): void
    {
        $this->useGateway(fails: false);

        app(SmsChannel::class)->send($this->recipient(), new LoginOtpNotification('482913', OtpChannel::SMS, 10));

        $this->assertCount(1, $this->sent);
        $this->assertSame('0599123456', $this->sent[0]['to']);
        $this->assertStringContainsString('482913', $this->sent[0]['message']);
    }

    public function test_a_failed_text_falls_back_to_email_when_allowed(): void
    {
        Notification::fake();
        $this->useGateway(fails: true);

        app(SmsChannel::class)->send(
            $this->recipient(),
            new LoginOtpNotification('482913', OtpChannel::SMS, 10, fallbackToEmail: true),
        );

        $this->assertSame([], $this->sent);
        Notification::assertSentOnDemand(
            LoginOtpNotification::class,
            fn (LoginOtpNotification $notification, array $channels): bool => $channels === ['mail']
                && (fn () => $this->code)->call($notification) === '482913',
        );
    }

    public function test_a_failed_text_without_fallback_is_reported_as_a_failure(): void
    {
        $this->useGateway(fails: true);

        $this->expectException(SmsDeliveryException::class);

        app(SmsChannel::class)->send($this->recipient(), new LoginOtpNotification('482913', OtpChannel::SMS, 10));
    }

    public function test_the_log_driver_never_writes_the_message(): void
    {
        Log::spy();
        SmsSettings::fake(['driver' => 'log', 'api_url' => '', 'api_key' => '', 'sender_id' => ''], false);

        app(SmsChannel::class)->send($this->recipient(), new LoginOtpNotification('482913', OtpChannel::SMS, 10));

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return ! str_contains($message.json_encode($context), '482913')
                && $context['to'] === '******3456';
        })->once();
    }

    public function test_the_notification_is_queued_encrypted(): void
    {
        $notification = new LoginOtpNotification('482913', OtpChannel::MAIL, 10);

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
        $this->assertNull($notification->emailFallback(), 'Email has nothing to fall back to.');
    }
}

<?php

namespace Modules\PPUDS\Support\Sms;

use Illuminate\Support\Facades\Log;
use Modules\PPUDS\Interfaces\SmsGatewayInterface;

/**
 * Pretends to send: records that a text would have gone out, for development
 * before a real provider is configured.
 *
 * Only the masked number and the length are written — never the text itself,
 * because sign-in codes travel in it and must never reach a log.
 */
class LogSmsGateway implements SmsGatewayInterface
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS (log driver): message not sent to a provider.', [
            'to' => SmsManager::mask($to),
            'length' => mb_strlen($message),
        ]);
    }
}

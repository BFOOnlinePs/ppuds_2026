<?php

namespace Modules\PPUDS\Interfaces;

use Modules\PPUDS\Support\Sms\SmsDeliveryException;

/**
 * One SMS provider. Adding a provider means adding one implementation and
 * registering it as a driver on SmsManager — nothing that sends texts knows
 * which provider is behind it.
 */
interface SmsGatewayInterface
{
    /**
     * @throws SmsDeliveryException when the provider did not accept the message.
     */
    public function send(string $to, string $message): void;
}

<?php

namespace Modules\PPUDS\Support\Sms;

use RuntimeException;

/**
 * The text could not be handed to the provider.
 *
 * The message never contains the text that was being sent: it ends up in logs
 * and failed-job records, and the text may hold a sign-in code.
 */
class SmsDeliveryException extends RuntimeException {}

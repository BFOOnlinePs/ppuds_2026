<?php

namespace Modules\PPUDS\DTOs;

use Modules\PPUDS\Enums\OtpChannel;

/**
 * What the sign-in screen is told after asking for a code. Identical whether
 * or not the email belongs to anyone, so it reveals nothing about accounts.
 */
readonly class OtpChallengeDTO
{
    public function __construct(
        public OtpChannel $channel,
        public int $expiresIn,
        public int $resendAfter,
        public int $codeLength,
    ) {}

    public function toArray(): array
    {
        return [
            'channel' => $this->channel->key(),
            'expires_in' => $this->expiresIn,
            'resend_after' => $this->resendAfter,
            'code_length' => $this->codeLength,
        ];
    }
}

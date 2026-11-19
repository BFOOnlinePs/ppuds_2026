<?php

namespace Modules\PPUDS\Enums;

/**
 * What a sign-in code was issued for. A code only works for the purpose it
 * was issued for, so e.g. a code-only sign-in code can never stand in for
 * the second step after a password.
 */
enum OtpPurpose: int
{
    // Email, then code — no password.
    case CODE_LOGIN = 1;

    // The second step, issued only after a correct password.
    case AFTER_PASSWORD = 2;

    // Started from a scanned company QR code; bound to that company.
    case COMPANY_QR = 3;
}

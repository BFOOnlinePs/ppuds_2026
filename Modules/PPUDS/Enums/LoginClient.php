<?php

namespace Modules\PPUDS\Enums;

/**
 * Where a sign-in happens. The dashboard and the mobile app each have their
 * own InternalLoginMode in AuthenticationSettings.
 */
enum LoginClient: string
{
    case WEB = 'web';
    case MOBILE = 'mobile';
}

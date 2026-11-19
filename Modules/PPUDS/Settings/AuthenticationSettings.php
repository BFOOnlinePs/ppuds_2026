<?php

namespace Modules\PPUDS\Settings;

use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\LoginClient;
use Modules\PPUDS\Enums\OtpChannel;
use Spatie\LaravelSettings\Settings;

/**
 * How people sign in through the internal login — on the dashboard and in
 * the mobile app separately — plus one-time codes and company QR codes.
 *
 * All of it belongs to the internal (SYSTEM) login only. None of it applies
 * while the system signs people in through the university realm, which is
 * checked by LoginOtpService rather than here.
 *
 * NOTE: properties use `//` comments, not docblocks, on purpose — Spatie's
 * PropertyReflector stops reading the native type once a property has a
 * docblock without @var, which leaves it uncast. See GeneralSettings. The
 * array property does carry a @var docblock, exactly like
 * GeneralSettings::$work_location_required_major_ids.
 */
class AuthenticationSettings extends Settings
{
    // How people sign in to the dashboard: password, code, either, or both.
    public InternalLoginMode $web_login_mode;

    // How people sign in from the mobile app — chosen independently of the web.
    public InternalLoginMode $mobile_login_mode;

    // Whether the mobile app may start a sign-in by scanning a company QR code.
    public bool $qr_login_enabled;

    /**
     * Role names allowed to sign in through a company QR code. Each of them
     * must also belong to the scanned company — see CompanyMembershipService.
     *
     * @var array<int, string>
     */
    public array $qr_login_roles;

    // How long a newly generated company QR code stays valid, in days. 0 = no expiry.
    public int $qr_code_validity_days;

    // Number of digits in a code.
    public int $otp_length;

    // How long a code can be used, in minutes.
    public int $otp_ttl_minutes;

    // Wrong guesses allowed on one code before it is burned.
    public int $otp_max_attempts;

    // Seconds before another code may be requested for the same email.
    public int $otp_resend_cooldown_seconds;

    // Codes that may be requested for the same email within an hour.
    public int $otp_max_sends_per_hour;

    public bool $otp_email_enabled;

    public bool $otp_sms_enabled;

    // Used whenever the user does not, or may not, pick a channel.
    public OtpChannel $otp_default_channel;

    // Whether the sign-in screen lets the user pick email or SMS.
    public bool $otp_allow_channel_choice;

    // Send the code by email instead when the SMS cannot be sent.
    public bool $otp_sms_fallback_to_email;

    // Upper bound on sign-in texts per day across the whole system, to cap the bill.
    public int $sms_daily_limit;

    public static function group(): string
    {
        return 'ppuds_authentication';
    }

    public function loginModeFor(LoginClient $client): InternalLoginMode
    {
        return $client === LoginClient::MOBILE ? $this->mobile_login_mode : $this->web_login_mode;
    }
}

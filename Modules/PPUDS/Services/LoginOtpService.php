<?php

namespace Modules\PPUDS\Services;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Core\Listeners\AuthActivitySubscriber;
use Modules\PPUDS\DTOs\OtpChallengeDTO;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\LoginOtp;
use Modules\PPUDS\Enums\InternalLoginMode;
use Modules\PPUDS\Enums\LoginClient;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Enums\OtpChannel;
use Modules\PPUDS\Enums\OtpPurpose;
use Modules\PPUDS\Exceptions\OtpLoginException;
use Modules\PPUDS\Notifications\LoginOtpNotification;
use Modules\PPUDS\Settings\AuthenticationSettings;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\Sms\SmsManager;
use Throwable;

/**
 * The internal login's sign-in rules, for the dashboard and the mobile app.
 *
 * Each of them follows its own InternalLoginMode: password only, one-time
 * code only, either one, or password then code (two-step). On top of that
 * the mobile app may start a code sign-in from a scanned company QR code.
 * None of it applies while the system signs people in through the
 * university SSO.
 *
 * Codes are bound to the purpose they were issued for (OtpPurpose), stored
 * as a keyed hash only and never written to a log. Nothing here reveals
 * whether an email belongs to anyone: requesting a code answers the same
 * either way, and a wrong email fails exactly like a wrong code.
 */
class LoginOtpService
{
    public function __construct(
        private readonly CompanyMembershipService $membership,
        private readonly SmsManager $sms,
    ) {}

    // ---------------------------------------------------------------------
    // What is allowed
    // ---------------------------------------------------------------------

    /** The internal login is in use — this feature never applies to the university SSO. */
    public function internalLoginInUse(): bool
    {
        return app(GeneralSettings::class)->login_method === LoginMethod::SYSTEM;
    }

    /**
     * The mode in force for the client. It falls back to the password, so
     * nobody is ever locked out, when:
     *  - the settings cannot be read (e.g. deployed before the migration ran);
     *  - the mode needs codes but no channel can deliver one.
     */
    public function mode(LoginClient $client): InternalLoginMode
    {
        try {
            if (! $this->internalLoginInUse()) {
                return InternalLoginMode::PASSWORD;
            }

            $mode = $this->settings()->loginModeFor($client);
            $noChannel = $mode->usesCodes() && $this->enabledChannels() === [];
        } catch (Throwable $e) {
            report($e);

            return InternalLoginMode::PASSWORD;
        }

        if ($noChannel) {
            Log::warning('Sign-in mode needs one-time codes but no delivery channel works; falling back to the password.', [
                'client' => $client->value,
                'mode' => $mode->key(),
            ]);

            return InternalLoginMode::PASSWORD;
        }

        return $mode;
    }

    public function passwordLoginAllowed(LoginClient $client): bool
    {
        return $this->mode($client)->allowsPassword();
    }

    /** Email, then code — without any password. */
    public function codeLoginAvailable(LoginClient $client): bool
    {
        return $this->mode($client)->allowsCodeOnly();
    }

    /** A correct password must be followed by a code (two-step). */
    public function codeAfterPasswordRequired(LoginClient $client): bool
    {
        return $this->mode($client)->requiresCodeAfterPassword();
    }

    /** The mobile app may start a code sign-in from a scanned company QR code. */
    public function qrLoginAvailable(): bool
    {
        try {
            return $this->internalLoginInUse()
                && $this->settings()->qr_login_enabled
                && $this->enabledChannels() !== [];
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * The mobile app may sign someone straight in from their own personal QR
     * code. Needs no delivery channel: no code is sent.
     */
    public function personalQrLoginAvailable(): bool
    {
        try {
            return $this->internalLoginInUse() && $this->settings()->personal_qr_login_enabled;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Everything a sign-in screen needs to know, in one go.
     *
     * Never throws: it is read by the login pages and the app's bootstrap
     * config, which must keep working even before the settings migration
     * has run — then it simply reads as "password only".
     *
     * @return array{mode: string, password_login: bool, code_login: bool, two_step: bool, company_qr_login: bool, personal_qr_login: bool, channels: array<int, string>, default_channel: ?string, channel_choice: bool, code_length: ?int}
     */
    public function availability(LoginClient $client): array
    {
        try {
            $mode = $this->mode($client);
            $usesCodes = $mode->usesCodes() || ($client === LoginClient::MOBILE && $this->qrLoginAvailable());

            return [
                'mode' => $mode->key(),
                'password_login' => $mode->allowsPassword(),
                'code_login' => $mode->allowsCodeOnly(),
                'two_step' => $mode->requiresCodeAfterPassword(),
                'company_qr_login' => $client === LoginClient::MOBILE && $this->qrLoginAvailable(),
                'personal_qr_login' => $client === LoginClient::MOBILE && $this->personalQrLoginAvailable(),
                'channels' => $usesCodes ? array_map(fn (OtpChannel $channel): string => $channel->key(), $this->enabledChannels()) : [],
                'default_channel' => $usesCodes ? $this->resolveChannel()?->key() : null,
                'channel_choice' => $usesCodes && $this->channelChoiceAllowed(),
                'code_length' => $usesCodes ? $this->settings()->otp_length : null,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'mode' => InternalLoginMode::PASSWORD->key(),
                'password_login' => true,
                'code_login' => false,
                'two_step' => false,
                'company_qr_login' => false,
                'personal_qr_login' => false,
                'channels' => [],
                'default_channel' => null,
                'channel_choice' => false,
                'code_length' => null,
            ];
        }
    }

    /** @return array<int, OtpChannel> */
    public function enabledChannels(): array
    {
        $settings = $this->settings();
        $channels = [];

        if ($settings->otp_email_enabled) {
            $channels[] = OtpChannel::MAIL;
        }

        if ($settings->otp_sms_enabled && $this->sms->canDeliver()) {
            $channels[] = OtpChannel::SMS;
        }

        return $channels;
    }

    /** Whether the sign-in screen should offer a choice of channel. */
    public function channelChoiceAllowed(): bool
    {
        return $this->settings()->otp_allow_channel_choice && count($this->enabledChannels()) > 1;
    }

    /**
     * The channel the code goes out on: the user's pick when choosing is
     * allowed, otherwise the default, otherwise whatever is enabled.
     */
    public function resolveChannel(?OtpChannel $requested = null): ?OtpChannel
    {
        $enabled = $this->enabledChannels();

        if ($requested && $this->channelChoiceAllowed() && in_array($requested, $enabled, true)) {
            return $requested;
        }

        $default = $this->settings()->otp_default_channel;

        return in_array($default, $enabled, true) ? $default : ($enabled[0] ?? null);
    }

    // ---------------------------------------------------------------------
    // Sending codes
    // ---------------------------------------------------------------------

    /**
     * A code for signing in without a password — or, with a company, for
     * signing in through that company's QR code. Sent only when the email
     * belongs to someone allowed to use it; the answer is the same either way.
     *
     * @throws OtpLoginException when this sign-in is switched off or the email asked too often.
     */
    public function request(string $email, ?OtpChannel $requested, ?Company $company, ?string $ip, LoginClient $client): OtpChallengeDTO
    {
        $allowed = $company ? $this->qrLoginAvailable() : $this->codeLoginAvailable($client);

        if (! $allowed) {
            throw OtpLoginException::unavailable();
        }

        $channel = $this->resolveChannel($requested) ?? throw OtpLoginException::unavailable();

        // Keyed by the email alone, before any lookup, so a limit hit says
        // nothing about whether the account exists.
        $this->throttleSending($email);

        $user = $this->findEligibleUser($email, $company);

        if ($user) {
            $this->issue($user, $channel, $company ? OtpPurpose::COMPANY_QR : OtpPurpose::CODE_LOGIN, $company, $ip);
        }

        return $this->challenge($channel);
    }

    /**
     * The second step of a two-step sign-in, for someone whose password was
     * just accepted. Only callers that verified the password may call this.
     *
     * @throws OtpLoginException when two-step sign-in is off or codes were asked for too often.
     */
    public function requestAfterPassword(User $user, ?OtpChannel $requested, ?string $ip, LoginClient $client): OtpChallengeDTO
    {
        if (! $this->codeAfterPasswordRequired($client)) {
            throw OtpLoginException::unavailable();
        }

        $channel = $this->resolveChannel($requested) ?? throw OtpLoginException::unavailable();

        $this->throttleSending((string) $user->email);
        $this->issue($user, $channel, OtpPurpose::AFTER_PASSWORD, null, $ip);

        return $this->challenge($channel);
    }

    // ---------------------------------------------------------------------
    // Checking codes
    // ---------------------------------------------------------------------

    /**
     * Checks a code-only (or company QR) sign-in and returns the person it
     * signs in. Signing them in — session or token — is the caller's job.
     *
     * @throws OtpLoginException when this sign-in is off or too many wrong codes were tried.
     * @throws ValidationException when the code is wrong, used, expired or not for this email.
     */
    public function verify(string $email, string $code, ?Company $company, string $guard, LoginClient $client): User
    {
        $allowed = $company ? $this->qrLoginAvailable() : $this->codeLoginAvailable($client);

        if (! $allowed) {
            throw OtpLoginException::unavailable();
        }

        return $this->check(
            $email,
            $this->findEligibleUser($email, $company),
            $code,
            $company ? OtpPurpose::COMPANY_QR : OtpPurpose::CODE_LOGIN,
            $company,
            $guard,
        );
    }

    /**
     * Checks the code of a two-step sign-in, for the person whose password
     * was accepted (taken from the session or the login ticket).
     *
     * @throws OtpLoginException
     * @throws ValidationException
     */
    public function verifyAfterPassword(User $user, string $code, string $guard, LoginClient $client): User
    {
        if (! $this->codeAfterPasswordRequired($client)) {
            throw OtpLoginException::unavailable();
        }

        return $this->check((string) $user->email, $user, $code, OtpPurpose::AFTER_PASSWORD, null, $guard);
    }

    // ---------------------------------------------------------------------
    // Two-step login ticket (mobile)
    // ---------------------------------------------------------------------

    /**
     * Proof, for the mobile app, that the password step succeeded. The app
     * sends it back with the code (and to resend the code), so the second
     * step can never be reached without the first. Encrypted with the app
     * key and short-lived: it expires with the code.
     */
    public function issueLoginTicket(User $user): string
    {
        return Crypt::encryptString(json_encode([
            'uid' => $user->getKey(),
            'exp' => now()->addMinutes($this->settings()->otp_ttl_minutes)->timestamp,
            'for' => OtpPurpose::AFTER_PASSWORD->value,
        ]));
    }

    public function userFromLoginTicket(?string $ticket): ?User
    {
        if (blank($ticket)) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($ticket), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (($payload['for'] ?? null) !== OtpPurpose::AFTER_PASSWORD->value || (int) ($payload['exp'] ?? 0) < now()->timestamp) {
            return null;
        }

        return User::query()->find($payload['uid'] ?? null);
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * The account behind the email. Anyone may sign in with a code; for a
     * company QR sign-in they must also belong to that company through one
     * of the allowed QR roles.
     */
    public function findEligibleUser(string $email, ?Company $company = null): ?User
    {
        $email = trim($email);

        if ($email === '') {
            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $company) {
            return $user;
        }

        return $this->membership->qualifyingRole($user, $company, $this->settings()->qr_login_roles) !== null ? $user : null;
    }

    /**
     * Keyed with the application key, so the stored hashes are useless to
     * anyone holding only the database. The user id is mixed in so the same
     * code never hashes alike for two people.
     */
    public function hash(int|string $userId, string $code): string
    {
        return hash_hmac('sha256', $userId.'|'.$code, (string) config('app.key'));
    }

    /**
     * @throws OtpLoginException
     * @throws ValidationException
     */
    protected function check(string $email, ?User $user, string $code, OtpPurpose $purpose, ?Company $company, string $guard): User
    {
        $settings = $this->settings();
        $limiterKey = $this->limiterKey('verify', $email);

        if (RateLimiter::tooManyAttempts($limiterKey, $settings->otp_max_attempts * 2)) {
            event(new Lockout(request()));

            throw OtpLoginException::throttled(RateLimiter::availableIn($limiterKey));
        }

        $otp = $user ? $this->latestUsableCode($user, $purpose, $company) : null;

        if (! $user || ! $otp) {
            // Still hash the input, so an unknown email costs the same time.
            $this->hash(0, $code);

            $this->fail($limiterKey, $guard, $user, $email);
        }

        // Counted before comparing, atomically, so parallel guesses cannot
        // slip past the attempt limit.
        $counted = LoginOtp::query()
            ->whereKey($otp->getKey())
            ->usable()
            ->where('attempts', '<', $settings->otp_max_attempts)
            ->increment('attempts');

        if (! $counted) {
            $this->invalidate($otp);
            $this->fail($limiterKey, $guard, $user, $email);
        }

        if (! hash_equals($otp->code_hash, $this->hash($user->getKey(), $this->normalizeCode($code)))) {
            if ($otp->attempts + 1 >= $settings->otp_max_attempts) {
                $this->invalidate($otp);
            }

            $this->fail($limiterKey, $guard, $user, $email);
        }

        // Single use: only one request can flip consumed_at from null.
        $consumed = LoginOtp::query()
            ->whereKey($otp->getKey())
            ->usable()
            ->update(['consumed_at' => now()]);

        if ($consumed !== 1) {
            $this->fail($limiterKey, $guard, $user, $email);
        }

        RateLimiter::clear($limiterKey);

        // Lets the activity log tell how this person signed in.
        request()->attributes->set(AuthActivitySubscriber::SIGN_IN_METHOD_ATTRIBUTE, $this->signInMethod($otp));

        // A code that arrived by email proves the address works.
        if ($otp->channel === OtpChannel::MAIL && ! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->saveQuietly();
        }

        return $user;
    }

    protected function issue(User $user, OtpChannel $channel, OtpPurpose $purpose, ?Company $company, ?string $ip): void
    {
        $settings = $this->settings();
        $channel = $this->deliverableChannel($user, $channel);

        if (! $channel) {
            Log::warning('Sign-in code not sent: no channel can reach this account.', ['user_id' => $user->getKey()]);

            return;
        }

        $code = $this->generateCode($settings->otp_length);

        DB::transaction(function () use ($user, $channel, $purpose, $company, $ip, $code, $settings): void {
            // A new code always replaces any earlier one still waiting.
            LoginOtp::query()
                ->where('user_id', $user->getKey())
                ->usable()
                ->update(['invalidated_at' => now()]);

            LoginOtp::create([
                'user_id' => $user->getKey(),
                'channel' => $channel,
                'purpose' => $purpose,
                'code_hash' => $this->hash($user->getKey(), $code),
                'company_id' => $company?->getKey(),
                'expires_at' => now()->addMinutes($settings->otp_ttl_minutes),
                'ip' => $ip,
            ]);
        });

        try {
            $user->notify((new LoginOtpNotification(
                $code,
                $channel,
                $settings->otp_ttl_minutes,
                $settings->otp_sms_fallback_to_email && $settings->otp_email_enabled,
            ))->locale(app()->getLocale()));
        } catch (Throwable $e) {
            // Reported, never surfaced: telling the caller would reveal that
            // the email belongs to someone. The code simply expires.
            report($e);
        }
    }

    protected function challenge(OtpChannel $channel): OtpChallengeDTO
    {
        $settings = $this->settings();

        return new OtpChallengeDTO(
            channel: $channel,
            expiresIn: $settings->otp_ttl_minutes * 60,
            resendAfter: $settings->otp_resend_cooldown_seconds,
            codeLength: $settings->otp_length,
        );
    }

    /**
     * Falls back to email when a text cannot go out to this person: no phone
     * on file, or today's SMS allowance is used up.
     */
    protected function deliverableChannel(User $user, OtpChannel $channel): ?OtpChannel
    {
        if ($channel !== OtpChannel::SMS) {
            return $channel;
        }

        $settings = $this->settings();
        $smsSentToday = LoginOtp::query()
            ->where('channel', OtpChannel::SMS->value)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if (filled($user->phone) && $smsSentToday < $settings->sms_daily_limit) {
            return OtpChannel::SMS;
        }

        if ($smsSentToday >= $settings->sms_daily_limit) {
            Log::warning('Daily sign-in SMS limit reached.', ['limit' => $settings->sms_daily_limit]);
        }

        return $settings->otp_sms_fallback_to_email && $settings->otp_email_enabled ? OtpChannel::MAIL : null;
    }

    /**
     * The newest usable code for exactly this purpose — and, for a company
     * QR sign-in, exactly this company.
     */
    protected function latestUsableCode(User $user, OtpPurpose $purpose, ?Company $company): ?LoginOtp
    {
        return LoginOtp::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose->value)
            ->usable()
            ->when(
                $company,
                fn ($query) => $query->where('company_id', $company->getKey()),
                fn ($query) => $query->whereNull('company_id'),
            )
            ->latest('id')
            ->first();
    }

    protected function signInMethod(LoginOtp $otp): string
    {
        $channel = $otp->channel === OtpChannel::SMS ? 'sms' : 'email';

        return match ($otp->purpose) {
            OtpPurpose::COMPANY_QR => 'company_qr',
            OtpPurpose::AFTER_PASSWORD => "password_and_{$channel}_code",
            default => "{$channel}_code",
        };
    }

    /**
     * @throws OtpLoginException
     */
    protected function throttleSending(string $email): void
    {
        $settings = $this->settings();
        $cooldownKey = $this->limiterKey('cooldown', $email);
        $hourlyKey = $this->limiterKey('hourly', $email);

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            throw OtpLoginException::throttled(RateLimiter::availableIn($cooldownKey));
        }

        if (RateLimiter::tooManyAttempts($hourlyKey, $settings->otp_max_sends_per_hour)) {
            throw OtpLoginException::throttled(RateLimiter::availableIn($hourlyKey));
        }

        RateLimiter::hit($cooldownKey, $settings->otp_resend_cooldown_seconds);
        RateLimiter::hit($hourlyKey, 3600);
    }

    /**
     * Every failure looks the same from outside: wrong email, wrong code,
     * expired, used or burned.
     *
     * @throws ValidationException
     */
    protected function fail(string $limiterKey, string $guard, ?User $user, string $email): never
    {
        RateLimiter::hit($limiterKey, 15 * 60);

        event(new Failed($guard, $user, ['email' => $email]));

        throw ValidationException::withMessages([
            'code' => __('The code is invalid or has expired.'),
        ]);
    }

    protected function invalidate(LoginOtp $otp): void
    {
        LoginOtp::query()->whereKey($otp->getKey())->whereNull('invalidated_at')->update(['invalidated_at' => now()]);
    }

    protected function generateCode(int $length): string
    {
        $length = max(4, min(8, $length));

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    protected function normalizeCode(string $code): string
    {
        return preg_replace('/\s+/', '', $code) ?? '';
    }

    protected function limiterKey(string $purpose, string $email): string
    {
        return 'ppuds-otp-'.$purpose.':'.sha1(mb_strtolower(trim($email)));
    }

    protected function settings(): AuthenticationSettings
    {
        return app(AuthenticationSettings::class);
    }
}

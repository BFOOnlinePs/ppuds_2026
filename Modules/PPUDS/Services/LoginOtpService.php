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
use Modules\PPUDS\Entities\UserLoginPreference;
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

    /**
     * One person's sign-in method — the most specific setting wins:
     *  1. their own (Users → edit → Sign-in Method);
     *  2. the first role rule that matches them (Settings, in order);
     *  3. the general setting for the client (dashboard / mobile app).
     * Same safety fallbacks to the password as mode().
     */
    public function modeForUser(User $user, LoginClient $client): InternalLoginMode
    {
        try {
            if (! $this->internalLoginInUse()) {
                return InternalLoginMode::PASSWORD;
            }

            $mode = $this->preferenceFor($user)?->login_mode
                ?? InternalLoginMode::tryFrom((int) $this->roleRuleValueFor($user, 'mode'))
                ?? $this->settings()->loginModeFor($client);

            $noChannel = $mode->usesCodes() && $this->enabledChannels() === [];
        } catch (Throwable $e) {
            report($e);

            return InternalLoginMode::PASSWORD;
        }

        return $noChannel ? InternalLoginMode::PASSWORD : $mode;
    }

    /**
     * The channel one person's codes go out on: their own, else their role's,
     * else the usual choice. A fixed channel that is switched off is ignored.
     */
    public function channelForUser(User $user, ?OtpChannel $requested = null): ?OtpChannel
    {
        $own = $this->ownChannelFor($user);

        return $own ?? $this->resolveChannel($requested);
    }

    /** Whether any person or role has a sign-in method of their own. */
    public function hasPersonalRules(): bool
    {
        try {
            return collect($this->settings()->role_login_rules)
                ->contains(fn (array $rule): bool => filled($rule['mode'] ?? null) || filled($rule['channel'] ?? null))
                || UserLoginPreference::query()
                    ->where(fn ($query) => $query->whereNotNull('login_mode')->orWhereNotNull('otp_channel'))
                    ->exists();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Whether the password has to be checked before it is known how the
     * person continues — their own or their role's method may differ from
     * the general one, or the general one is two-step.
     */
    public function perUserRulesMatter(LoginClient $client): bool
    {
        return $this->hasPersonalRules() || $this->mode($client)->requiresCodeAfterPassword();
    }

    /** Someone may sign in with a password on this client. */
    public function passwordLoginAllowed(LoginClient $client): bool
    {
        return $this->anyPossibleMode($client, fn (InternalLoginMode $mode): bool => $mode->allowsPassword());
    }

    /** Someone may sign in with email, then code — without any password. */
    public function codeLoginAvailable(LoginClient $client): bool
    {
        return $this->anyPossibleMode($client, fn (InternalLoginMode $mode): bool => $mode->allowsCodeOnly());
    }

    /** Someone's correct password is followed by a code (two-step). */
    public function twoStepPossible(LoginClient $client): bool
    {
        return $this->anyPossibleMode($client, fn (InternalLoginMode $mode): bool => $mode->requiresCodeAfterPassword());
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
     * The flags tell what anyone may use (general setting, role rules and
     * personal settings together); `mode` is the general setting, and
     * `per_user` says the screen should ask for the email first and call
     * identify() to learn that person's method.
     *
     * @return array{mode: string, per_user: bool, password_login: bool, code_login: bool, two_step: bool, company_qr_login: bool, personal_qr_login: bool, channels: array<int, string>, default_channel: ?string, channel_choice: bool, code_length: ?int}
     */
    public function availability(LoginClient $client): array
    {
        try {
            $mode = $this->mode($client);
            $usesCodes = $this->anyPossibleMode($client, fn (InternalLoginMode $mode): bool => $mode->usesCodes())
                || ($client === LoginClient::MOBILE && $this->qrLoginAvailable());

            return [
                'mode' => $mode->key(),
                'per_user' => $this->internalLoginInUse() && $this->hasPersonalRules(),
                'password_login' => $this->passwordLoginAllowed($client),
                'code_login' => $this->codeLoginAvailable($client),
                'two_step' => $this->twoStepPossible($client),
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
                'per_user' => false,
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

    /**
     * How one account signs in — for a screen that asks for the email first.
     *
     * An unknown email answers with the general setting, exactly like an
     * account that has no method of its own, so most accounts cannot be told
     * apart from non-existent ones. Only an account whose own or role method
     * differs from the general one stands out — the unavoidable cost of
     * choosing the method per person.
     *
     * @return array{mode: string, password_login: bool, code_login: bool, two_step: bool, channels: array<int, string>, default_channel: ?string, channel_choice: bool, code_length: ?int}
     */
    public function identify(string $email, LoginClient $client): array
    {
        $user = $this->findEligibleUser($email);
        $mode = $user ? $this->modeForUser($user, $client) : $this->mode($client);
        $usesCodes = $mode->usesCodes();
        $ownChannel = $user ? $this->ownChannelFor($user) : null;

        return [
            'mode' => $mode->key(),
            'password_login' => $mode->allowsPassword(),
            'code_login' => $mode->allowsCodeOnly(),
            'two_step' => $mode->requiresCodeAfterPassword(),
            'channels' => $usesCodes ? array_map(fn (OtpChannel $channel): string => $channel->key(), $ownChannel ? [$ownChannel] : $this->enabledChannels()) : [],
            'default_channel' => $usesCodes ? ($ownChannel ?? $this->resolveChannel())?->key() : null,
            'channel_choice' => $usesCodes && ! $ownChannel && $this->channelChoiceAllowed(),
            'code_length' => $usesCodes ? $this->settings()->otp_length : null,
        ];
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

        // A code-only sign-in is only for people whose own method allows it.
        if ($user && ! $company && ! $this->modeForUser($user, $client)->allowsCodeOnly()) {
            $user = null;
        }

        if ($user) {
            $channel = $this->channelForUser($user, $requested) ?? $channel;

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
        if (! $this->modeForUser($user, $client)->requiresCodeAfterPassword()) {
            throw OtpLoginException::unavailable();
        }

        $channel = $this->channelForUser($user, $requested) ?? throw OtpLoginException::unavailable();

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

        $user = $this->findEligibleUser($email, $company);

        // Checked again on use: the person's method may have changed since.
        if ($user && ! $company && ! $this->modeForUser($user, $client)->allowsCodeOnly()) {
            $user = null;
        }

        return $this->check(
            $email,
            $user,
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
        if (! $this->modeForUser($user, $client)->requiresCodeAfterPassword()) {
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

    /** @var array<int|string, UserLoginPreference|null> */
    private array $preferences = [];

    protected function preferenceFor(User $user): ?UserLoginPreference
    {
        return $this->preferences[$user->getKey()] ??= UserLoginPreference::query()
            ->where('user_id', $user->getKey())
            ->first();
    }

    /**
     * A field of the first role rule that matches the person and sets it.
     * Rules are kept in the order the administrator arranged them.
     */
    protected function roleRuleValueFor(User $user, string $field): ?int
    {
        foreach ($this->settings()->role_login_rules as $rule) {
            if (filled($rule['role'] ?? null) && filled($rule[$field] ?? null) && $user->hasRole($rule['role'])) {
                return (int) $rule[$field];
            }
        }

        return null;
    }

    /** The channel fixed for this person or their role, while it is switched on. */
    protected function ownChannelFor(User $user): ?OtpChannel
    {
        $own = $this->preferenceFor($user)?->otp_channel
            ?? OtpChannel::tryFrom((int) $this->roleRuleValueFor($user, 'channel'));

        return $own && in_array($own, $this->enabledChannels(), true) ? $own : null;
    }

    /**
     * Whether any mode someone could be under on this client — the general
     * one, a role rule or a personal setting — passes the check.
     */
    protected function anyPossibleMode(LoginClient $client, \Closure $check): bool
    {
        foreach ($this->possibleModes($client) as $mode) {
            if ($check($mode)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, InternalLoginMode> */
    protected function possibleModes(LoginClient $client): array
    {
        $general = $this->mode($client);

        try {
            if (! $this->internalLoginInUse()) {
                return [$general];
            }

            $values = collect($this->settings()->role_login_rules)
                ->pluck('mode')
                ->merge(UserLoginPreference::query()->whereNotNull('login_mode')->distinct()->pluck('login_mode'))
                ->map(fn ($mode) => $mode instanceof InternalLoginMode ? $mode : InternalLoginMode::tryFrom((int) $mode))
                ->filter();

            $noChannel = $this->enabledChannels() === [];
        } catch (Throwable $e) {
            report($e);

            return [$general];
        }

        // A personal mode that needs codes reads as the password when nothing
        // can deliver them — the same fallback modeForUser() applies.
        return $values
            ->map(fn (InternalLoginMode $mode) => $mode->usesCodes() && $noChannel ? InternalLoginMode::PASSWORD : $mode)
            ->push($general)
            ->unique(fn (InternalLoginMode $mode) => $mode->value)
            ->values()
            ->all();
    }

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

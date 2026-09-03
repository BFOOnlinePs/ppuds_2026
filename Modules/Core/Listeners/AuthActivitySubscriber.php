<?php

namespace Modules\Core\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;

/**
 * Records authentication events in the activity log under the `auth` log
 * name, so successful sign-ins, sign-outs and failed attempts show up in the
 * same place as every other audited action.
 *
 * Credentials are never written: only the login identifier is kept, never the
 * submitted password.
 */
class AuthActivitySubscriber
{
    /** The activity log name every authentication entry is written under. */
    public const LOG_NAME = 'auth';

    public const EVENT_LOGIN = 'login';

    public const EVENT_LOGOUT = 'logout';

    public const EVENT_FAILED_LOGIN = 'failed_login';

    public const EVENT_LOCKOUT = 'lockout';

    public const EVENT_TOKEN_REFRESHED = 'token_refreshed';

    public const EVENT_PASSWORD_RESET = 'password_reset';

    public const EVENT_REGISTERED = 'registered';

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            OtherDeviceLogout::class => 'handleOtherDeviceLogout',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
            PasswordReset::class => 'handlePasswordReset',
            Registered::class => 'handleRegistered',
        ];
    }

    public function handleLogin(Login $event): void
    {
        $this->record(
            self::EVENT_LOGIN,
            'Signed in',
            $event->user,
            [
                'guard' => $event->guard,
                'remember' => (bool) ($event->remember ?? false),
            ],
        );
    }

    public function handleLogout(Logout $event): void
    {
        $this->record(
            self::EVENT_LOGOUT,
            'Signed out',
            $event->user,
            ['guard' => $event->guard],
        );
    }

    public function handleOtherDeviceLogout(OtherDeviceLogout $event): void
    {
        $this->record(
            self::EVENT_LOGOUT,
            'Signed out from other devices',
            $event->user,
            ['guard' => $event->guard],
        );
    }

    public function handleFailed(Failed $event): void
    {
        $this->record(
            self::EVENT_FAILED_LOGIN,
            'Failed sign in attempt',
            $event->user,
            [
                'guard' => $event->guard,
                // Only the identifier — never the submitted password.
                'login' => $this->loginIdentifier($event->credentials),
                'user_exists' => $event->user !== null,
            ],
        );
    }

    public function handleLockout(Lockout $event): void
    {
        $this->record(
            self::EVENT_LOCKOUT,
            'Sign in locked out after too many attempts',
            null,
            ['login' => $this->loginIdentifier($event->request->only($this->identifierKeys()))],
        );
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->record(self::EVENT_PASSWORD_RESET, 'Password reset', $event->user);
    }

    public function handleRegistered(Registered $event): void
    {
        $this->record(self::EVENT_REGISTERED, 'Account registered', $event->user);
    }

    /**
     * Records a renewed access token.
     *
     * Called directly instead of through an event, because the framework has
     * none for a refresh. It gets its own event name rather than reusing
     * Login: a renewal happens every few minutes per device, and counting it
     * as a sign-in would bury the real ones under noise.
     */
    public function recordTokenRefresh(?Authenticatable $user, array $properties = []): void
    {
        $this->record(self::EVENT_TOKEN_REFRESHED, 'Token refreshed', $user, $properties);
    }

    /**
     * Writes one entry. The user, when known, is both the causer and the
     * subject, so an admin reviewing a person sees their sign-ins alongside
     * the changes made to their record.
     */
    protected function record(string $event, string $description, ?Authenticatable $user, array $properties = []): void
    {
        if (! config('activitylog.enabled', true)) {
            return;
        }

        $activity = activity(self::LOG_NAME)
            ->event($event)
            ->withProperties(array_merge($this->requestProperties(), array_filter(
                $properties,
                fn (mixed $value): bool => $value !== null,
            )));

        if ($user instanceof \Illuminate\Database\Eloquent\Model) {
            $activity->causedBy($user)->performedOn($user);
        }

        $activity->log($description);
    }

    /** Where the request came from — the part an audit actually needs. */
    protected function requestProperties(): array
    {
        $request = request();

        if (! $request instanceof Request) {
            return [];
        }

        $userAgent = (string) $request->userAgent();

        return array_filter([
            'ip' => $request->ip(),
            'user_agent' => $userAgent !== '' ? mb_substr($userAgent, 0, 500) : null,
            'browser' => $this->browser($userAgent),
            'platform' => $this->platform($userAgent),
            // The mobile app names its own device; that is far better than
            // anything guessable from a User-Agent, which for a Dart HTTP
            // client carries no device information at all.
            'device_name' => $this->deviceName($request),
            'url' => mb_substr((string) $request->fullUrl(), 0, 500),
            'method' => $request->method(),
        ], fn (mixed $value): bool => filled($value));
    }

    /** @param array<string, mixed> $credentials */
    protected function loginIdentifier(array $credentials): ?string
    {
        foreach ($this->identifierKeys() as $key) {
            if (filled($credentials[$key] ?? null) && is_scalar($credentials[$key])) {
                return mb_substr((string) $credentials[$key], 0, 255);
            }
        }

        return null;
    }

    /** @return array<int, string> */
    protected function identifierKeys(): array
    {
        return array_values(array_unique(array_filter([
            config('fortify.username', 'email'),
            'email',
            'username',
            'phone',
            'student_number',
        ])));
    }

    /**
     * The device the app reported for itself, when it sent one.
     *
     * Only read on requests that carry it, and length-capped, since this is
     * client-supplied text going straight into the audit trail.
     */
    protected function deviceName(Request $request): ?string
    {
        $deviceName = $request->input('device_name');

        if (! is_string($deviceName)) {
            return null;
        }

        $deviceName = trim($deviceName);

        return $deviceName !== '' ? mb_substr($deviceName, 0, 120) : null;
    }

    protected function browser(string $userAgent): ?string
    {
        return $this->firstMatch($userAgent, [
            'Edge' => '/Edg[e]?\//i',
            'Opera' => '/OPR\/|Opera/i',
            'Samsung Internet' => '/SamsungBrowser/i',
            // Ahead of Chrome/Safari: mobile HTTP clients often borrow those
            // names, and knowing it is the app matters more than the engine.
            'Mobile App' => '/Dart\/|Flutter|okhttp|CFNetwork|Alamofire/i',
            'Chrome' => '/Chrome|CriOS/i',
            'Firefox' => '/Firefox|FxiOS/i',
            'Safari' => '/Safari/i',
            'Internet Explorer' => '/MSIE|Trident/i',
        ]);
    }

    protected function platform(string $userAgent): ?string
    {
        return $this->firstMatch($userAgent, [
            'Android' => '/Android|okhttp/i',
            // Dart on iOS reports through Darwin / CFNetwork rather than iPhone.
            'iOS' => '/iPhone|iPad|iPod|CFNetwork|Darwin/i',
            'Windows' => '/Windows/i',
            'macOS' => '/Macintosh|Mac OS X/i',
            'Linux' => '/Linux/i',
        ]);
    }

    /** @param array<string, string> $patterns */
    protected function firstMatch(string $subject, array $patterns): ?string
    {
        if ($subject === '') {
            return null;
        }

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $subject)) {
                return $label;
            }
        }

        return null;
    }
}

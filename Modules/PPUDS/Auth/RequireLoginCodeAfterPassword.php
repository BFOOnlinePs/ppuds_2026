<?php

namespace Modules\PPUDS\Auth;

use Illuminate\Contracts\Auth\StatefulGuard;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\LoginRateLimiter;
use Modules\PPUDS\Enums\LoginClient;
use Modules\PPUDS\Exceptions\OtpLoginException;
use Modules\PPUDS\Http\Controllers\OtpLoginController;
use Modules\PPUDS\Services\LoginOtpService;

/**
 * A step in Fortify's login pipeline that applies the person's own sign-in
 * method once their password is known to be right:
 *  - code only            → refused here: their account signs in with a code;
 *  - password then code   → a code is sent and the code page takes over; the
 *                           session only starts once that code is confirmed;
 *  - anything else        → the login carries on as before.
 *
 * The password is checked with Fortify's own validation (same failure
 * message, same rate limiter, same Failed event), so a wrong password still
 * reveals nothing about the account's method. When neither personal nor role
 * methods exist and the general mode is not two-step, the step is skipped.
 */
class RequireLoginCodeAfterPassword extends RedirectIfTwoFactorAuthenticatable
{
    public function __construct(StatefulGuard $guard, LoginRateLimiter $limiter, private readonly LoginOtpService $otp)
    {
        parent::__construct($guard, $limiter);
    }

    public function handle($request, $next)
    {
        if (! $this->otp->perUserRulesMatter(LoginClient::WEB)) {
            return $next($request);
        }

        $user = $this->validateCredentials($request);
        $mode = $this->otp->modeForUser($user, LoginClient::WEB);

        if (! $mode->allowsPassword()) {
            return redirect()->route('login.otp')
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('Your account signs in with a one-time code, not a password. Enter your email to receive one.')]);
        }

        if (! $mode->requiresCodeAfterPassword()) {
            return $next($request);
        }

        try {
            $challenge = $this->otp->requestAfterPassword($user, null, $request->ip(), LoginClient::WEB);
        } catch (OtpLoginException $e) {
            return back()->withInput($request->only('email'))->withErrors(['email' => $e->getMessage()]);
        }

        // Correct password: clear Fortify's failed-attempt counter like a real login would.
        $this->limiter->clear($request);

        OtpLoginController::rememberPendingSignIn($request, $user, $challenge, $request->boolean('remember'));

        return redirect()->route('login.otp');
    }
}

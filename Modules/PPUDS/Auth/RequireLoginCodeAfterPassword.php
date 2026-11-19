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
 * A step in Fortify's login pipeline for the "password, then code" mode.
 *
 * It checks the password with Fortify's own validation (same failure
 * message, same rate limiter, same Failed event), and instead of signing the
 * person in, sends them a one-time code and hands over to the code page. The
 * session is only started once that code is confirmed there.
 *
 * In every other mode it simply lets the login carry on as before.
 */
class RequireLoginCodeAfterPassword extends RedirectIfTwoFactorAuthenticatable
{
    public function __construct(StatefulGuard $guard, LoginRateLimiter $limiter, private readonly LoginOtpService $otp)
    {
        parent::__construct($guard, $limiter);
    }

    public function handle($request, $next)
    {
        if (! $this->otp->codeAfterPasswordRequired(LoginClient::WEB)) {
            return $next($request);
        }

        $user = $this->validateCredentials($request);

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

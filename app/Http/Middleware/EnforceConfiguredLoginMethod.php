<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\PPUDS\Enums\LoginClient;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Services\LoginOtpService;
use Modules\PPUDS\Settings\GeneralSettings;
use Symfony\Component\HttpFoundation\Response;

class EnforceConfiguredLoginMethod
{
    public function handle(Request $request, Closure $next): Response
    {
        $loginMethod = app(GeneralSettings::class)->login_method;

        if ($loginMethod === LoginMethod::PPU && $request->routeIs('login.store')) {
            return redirect()->route('keycloak.redirect');
        }

        if ($loginMethod === LoginMethod::SYSTEM && $request->routeIs('keycloak.*')) {
            return redirect()->route('login');
        }

        // While the dashboard signs in with the code only, a password is never
        // accepted — not even when posted straight to the login route.
        if ($loginMethod === LoginMethod::SYSTEM
            && $request->routeIs('login.store')
            && ! app(LoginOtpService::class)->passwordLoginAllowed(LoginClient::WEB)) {
            return redirect()->route('login.otp');
        }

        return $next($request);
    }
}

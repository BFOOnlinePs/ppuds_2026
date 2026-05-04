<?php

namespace Modules\PPUDS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Modules\PPUDS\Actions\AuthenticateViaKeycloakAction;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Settings\GeneralSettings;

class KeycloakAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('keycloak')->redirect();
    }

    public function callback(AuthenticateViaKeycloakAction $authAction)
    {
        try {
            $keycloakUser = Socialite::driver('keycloak')->user();
            $authAction->execute($keycloakUser);

            return redirect()->route('home');
        } catch (\Exception $e) {
            Log::error('Keycloak Auth Failed: '.$e->getMessage());

            return redirect()->route('login')->withErrors([
                'auth' => 'فشل في تسجيل الدخول عبر نظام الجامعة.',
            ]);
        }
    }

    public function logout(Request $request)
    {
        $loginMethod = app(GeneralSettings::class)->login_method;

        $this->logoutFromLaravel($request);

        if ($loginMethod === LoginMethod::SYSTEM) {
            return redirect()->route('login');
        }

        return redirect($this->keycloakLogoutUrl());
    }

    protected function logoutFromLaravel(Request $request): void
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    protected function keycloakLogoutUrl(): string
    {
        $clientId = config('services.keycloak.client_id');
        $baseUrl = config('services.keycloak.base_url');
        $realm = config('services.keycloak.realms');

        $redirectUri = urlencode(url('/login'));

        return "{$baseUrl}/realms/{$realm}/protocol/openid-connect/logout?client_id={$clientId}&post_logout_redirect_uri={$redirectUri}";
    }
}

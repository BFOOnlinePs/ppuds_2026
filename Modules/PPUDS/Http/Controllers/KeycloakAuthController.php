<?php

namespace Modules\PPUDS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Modules\PPUDS\Actions\AuthenticateViaKeycloakAction;

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
            Log::error('Keycloak Auth Failed: ' . $e->getMessage());

            return redirect()->route('login')->withErrors([
                'auth' => 'فشل في تسجيل الدخول عبر نظام الجامعة.'
            ]);
        }
    }

    public function logout(Request $request)
    {
        // 1. تسجيل الخروج محلياً من نظام لارافيل
        Auth::logout();

        // تنظيف الجلسة وحمايتها من هجمات (Session Fixation)
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 2. بناء رابط تسجيل الخروج الخاص بالجامعة
        $clientId = config('services.keycloak.client_id');
        $baseUrl = config('services.keycloak.base_url');
        $realm = config('services.keycloak.realms');

        // الرابط الذي ستعود إليه بعد الخروج من الجامعة (يجب أن يكون مدعوماً في إعداداتهم)
        $redirectUri = urlencode(url('/login'));

        // الرابط القياسي لـ Keycloak لإنهاء الجلسة
        $keycloakLogoutUrl = "{$baseUrl}/realms/{$realm}/protocol/openid-connect/logout?client_id={$clientId}&post_logout_redirect_uri={$redirectUri}";

        // 3. التوجيه إلى الجامعة
        return redirect($keycloakLogoutUrl);
    }
}

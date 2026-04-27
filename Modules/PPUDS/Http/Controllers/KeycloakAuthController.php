<?php

namespace Modules\PPUDS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
}

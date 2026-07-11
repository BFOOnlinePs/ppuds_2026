<?php

namespace Modules\PPUDS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Modules\Core\Actions\StoreDeviceTokenAction;
use Modules\PPUDS\Actions\AuthenticateViaKeycloakAction;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Services\PpuApiService;
use Modules\PPUDS\Settings\GeneralSettings;

class KeycloakAuthController extends Controller
{
    public function redirect(Request $request)
    {
        if (! $this->usesKeycloakLogin()) {
            return redirect()->route('login');
        }

        $this->rememberDeviceTokenForCallback($request);

        return Socialite::driver('keycloak')->redirect();
    }

    public function callback(
        Request $request,
        AuthenticateViaKeycloakAction $authAction,
        StoreDeviceTokenAction $storeDeviceToken
    ) {
        if (! $this->usesKeycloakLogin()) {
            return redirect()->route('login');
        }

        try {
            $keycloakUser = Socialite::driver('keycloak')->user();
            $user = $authAction->execute($keycloakUser);

            $storeDeviceToken->execute(
                $user,
                $request->session()->pull('keycloak_fcm_token'),
                $request->session()->pull('keycloak_device_name'),
            );

            return redirect()->intended(route('home'));
        } catch (ValidationException $e) {
            if ($this->usesKeycloakLogin()) {
                return redirect($this->keycloakLogoutUrl());
            }

            return redirect()->route('login')->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Keycloak Auth Failed: '.$e->getMessage());

            if ($this->usesKeycloakLogin()) {
                return redirect($this->keycloakLogoutUrl());
            }

            return redirect()->route('login')->withErrors([
                'auth' => 'فشل في تسجيل الدخول عبر نظام الجامعة.',
            ]);
        }
    }

    public function logout(Request $request)
    {
        $loginMethod = app(GeneralSettings::class)->login_method;
        $userId = auth()->id();
        $refreshToken = $request->session()->get('keycloak_refresh_token');

        if ($loginMethod === LoginMethod::PPU) {
            app(PpuApiService::class)->revokeRefreshToken($refreshToken, $userId);
            app(PpuApiService::class)->forgetTokenPair($userId);
        }

        $this->logoutFromLaravel($request);

        if ($loginMethod === LoginMethod::SYSTEM) {
            return redirect()->route('login');
        }

        return redirect($this->keycloakLogoutUrl());
    }

    protected function rememberDeviceTokenForCallback(Request $request): void
    {
        $fcmToken = $request->query('fcm_token');

        if (! is_string($fcmToken) || trim($fcmToken) === '') {
            $request->session()->forget(['keycloak_fcm_token', 'keycloak_device_name']);

            return;
        }

        $deviceName = $request->query('device_name');
        $deviceName = is_string($deviceName) && trim($deviceName) !== ''
            ? trim($deviceName)
            : ($request->userAgent() ?: 'Unknown Device');

        $request->session()->put('keycloak_fcm_token', trim($fcmToken));
        $request->session()->put('keycloak_device_name', $deviceName);
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

    protected function usesKeycloakLogin(): bool
    {
        return app(GeneralSettings::class)->login_method === LoginMethod::PPU;
    }
}

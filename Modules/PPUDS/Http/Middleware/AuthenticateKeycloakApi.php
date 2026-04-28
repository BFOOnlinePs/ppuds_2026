<?php

namespace Modules\PPUDS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\PPUDS\Services\KeycloakTokenValidator;
use Modules\PPUDS\Actions\AuthenticateViaKeycloakAction;
use Exception;

class AuthenticateKeycloakApi
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated - Bearer Token Missing'], 401);
        }

        try {
            // التحقق من التوكن
            $validator = app(KeycloakTokenValidator::class);
            $payload = $validator->validate($token);

            // استخدام الـ Action الخاص بك لمزامنة المستخدم
            // سنقوم بتعديله ليقبل الـ Payload مباشرة
            $authAction = app(AuthenticateViaKeycloakAction::class);
            $user = $authAction->executeFromToken($payload);

            // ربط المستخدم بالطلب الحالي (Stateless)
            Auth::setUser($user);

        } catch (Exception $e) {
            return response()->json(['message' => 'Unauthorized: ' . $e->getMessage()], 401);
        }

        return $next($request);
    }
}

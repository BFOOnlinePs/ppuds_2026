<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Validation\ValidationException;
use Modules\Core\Traits\ApiResponse;
use Modules\Core\Transformers\V1\UserResource;
use Modules\PPUDS\Actions\AuthenticateViaKeycloakAction;
use Modules\PPUDS\Http\Requests\Auth\UniversityLoginRequest;
use Modules\PPUDS\Http\Requests\Auth\UniversityRefreshRequest;
use Modules\PPUDS\Services\PpuApiService;

/**
 * Signs the mobile app in through the university's Keycloak realm.
 *
 * The app used to call the realm directly, which meant this system never saw
 * the sign-in and could not record it. Routing the call through here keeps
 * the token exactly as the realm issued it while making both successful and
 * failed attempts visible in the activity log.
 */
class UniversityLoginController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Post(
     *     path="/api/v1/auth/university-login",
     *     summary="Sign in through the university (Keycloak)",
     *     description="Signs the mobile app in against the university realm using the password grant and returns the realm's own token. Routing the call through this endpoint is what makes the sign-in appear in the activity log; successful and failed attempts are both recorded.",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"username","password"},
     *
     *             @OA\Property(property="username", type="string", example="202010001", description="University username or student number"),
     *             @OA\Property(property="password", type="string", format="password", example="secret12345"),
     *             @OA\Property(property="auth_type", type="string", nullable=true, example="otp", description="Realm two-factor parameter, forwarded only when sent"),
     *             @OA\Property(property="otp", type="string", nullable=true, example="123456", description="One-time code, forwarded only when sent"),
     *             @OA\Property(property="device_name", type="string", nullable=true, example="Samsung S23"),
     *             @OA\Property(property="fcm_token", type="string", nullable=true, example="fcm-token-value", description="Stored for push notifications when supplied")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Signed in successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User logged in successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", description="The university access token, unmodified", example="eyJhbGciOiJSUzI1NiIsInR5cCI6..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="refresh_token", type="string", nullable=true, example="eyJhbGciOiJIUzUxMiIsInR5cCI6..."),
     *                 @OA\Property(property="expires_in", type="integer", nullable=true, example=300),
     *                 @OA\Property(property="user", type="object")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Rejected by the university. The realm's own error is passed through so the app can tell a wrong password from an OTP challenge.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid user credentials"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="invalid_grant"),
     *                 @OA\Property(property="error_description", type="string", example="Invalid user credentials")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Signed in at the university but no matching local account, or the account is blocked"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=503, description="The university system could not be reached")
     * )
     */
    public function login(UniversityLoginRequest $request, PpuApiService $ppuApi, AuthenticateViaKeycloakAction $action)
    {
        $username = trim((string) $request->input('username'));

        $result = $ppuApi->requestPasswordGrantToken(
            $username,
            (string) $request->input('password'),
            $request->input('auth_type'),
            $request->input('otp'),
        );

        if (! $result['ok']) {
            return $this->reject($username, $result);
        }

        $accessToken = $result['data']['access_token'] ?? null;

        if (! $accessToken) {
            return $this->reject($username, $result);
        }

        // Maps the realm's identity onto the local account, applying the same
        // blocked-role and student-number rules the browser sign-in uses.
        try {
            $user = $action->resolveUserFromToken($accessToken);
        } catch (ValidationException $e) {
            event(new Failed($this->guardName(), null, ['username' => $username]));

            return $this->errorResponse($e->validator->errors()->first(), 403);
        }

        $refreshToken = $result['data']['refresh_token'] ?? null;

        $ppuApi->storeTokenPair($accessToken, $refreshToken, $user->id, $ppuApi->mobileClientId());

        $this->rememberDeviceToken($request, $user);

        // What puts the sign-in into the activity log.
        event(new Login($this->guardName(), $user, false));

        return $this->successResponse([
            'token' => $accessToken,
            'token_type' => 'Bearer',
            'refresh_token' => $refreshToken,
            'expires_in' => $result['data']['expires_in'] ?? null,
            'user' => new UserResource($user->load('studentProfile')),
        ], __('User logged in successfully'));
    }

    /**
     * Renews the app's token pair against the university realm.
     *
     * Unauthenticated on purpose: it is called precisely when the access
     * token has expired, and the refresh token is the credential. No Login
     * event is raised — a refresh is not a new sign-in, and logging it as one
     * would bury the real sign-ins under noise.
     */
    /**
     * @OA\Post(
     *     path="/api/v1/auth/university-refresh",
     *     summary="Renew the university token",
     *     description="Exchanges a refresh token for a new access token against the university realm, and keeps the pair stored on the server in step. Deliberately unauthenticated: it is called once the access token has expired, and the refresh token is itself the credential. A refresh is not recorded in the activity log, since it is not a new sign-in.",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *
     *             @OA\Property(property="refresh_token", type="string", example="eyJhbGciOiJIUzUxMiIsInR5cCI6...")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Token renewed",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token refreshed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", example="eyJhbGciOiJSUzI1NiIsInR5cCI6..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="refresh_token", type="string", nullable=true),
     *                 @OA\Property(property="expires_in", type="integer", nullable=true, example=300)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="The refresh token has expired or been revoked; the app must sign in again"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=503, description="The university system could not be reached")
     * )
     */
    public function refresh(UniversityRefreshRequest $request, PpuApiService $ppuApi, AuthenticateViaKeycloakAction $action)
    {
        $result = $ppuApi->refreshPasswordGrantToken((string) $request->input('refresh_token'));

        $accessToken = $result['data']['access_token'] ?? null;

        if (! $result['ok'] || ! $accessToken) {
            $error = $result['data']['error'] ?? null;

            return $this->errorResponse(
                $result['data']['error_description'] ?? __('Your session has expired. Please sign in again.'),
                $result['status'] === 503 ? 503 : 401,
                ['error' => $error],
            );
        }

        $refreshToken = $result['data']['refresh_token'] ?? $request->input('refresh_token');

        // Keeps the stored pair in step so server-side university calls made
        // on this user's behalf keep working.
        try {
            $user = $action->resolveUserFromToken($accessToken);
            $ppuApi->storeTokenPair($accessToken, $refreshToken, $user->id, $ppuApi->mobileClientId());
        } catch (ValidationException) {
            // The realm still renewed the token; the local account just could
            // not be matched. Hand the token back rather than locking the app
            // out over a bookkeeping problem.
        }

        return $this->successResponse([
            'token' => $accessToken,
            'token_type' => 'Bearer',
            'refresh_token' => $refreshToken,
            'expires_in' => $result['data']['expires_in'] ?? null,
        ], __('Token refreshed successfully'));
    }

    /**
     * Records the failed attempt and hands the realm's own error back
     * untouched, so the app can tell a wrong password from an OTP challenge.
     */
    private function reject(string $username, array $result)
    {
        event(new Failed($this->guardName(), null, ['username' => $username]));

        $error = $result['data']['error'] ?? null;
        $description = $result['data']['error_description'] ?? null;

        $message = match ($error) {
            'unreachable' => __('Could not reach the university system. Please try again.'),
            'invalid_grant' => $description ?: __('The provided credentials do not match our records.'),
            default => $description ?: __('The provided credentials do not match our records.'),
        };

        return $this->errorResponse($message, $result['status'] === 503 ? 503 : 401, [
            'error' => $error,
            'error_description' => $description,
        ]);
    }

    private function rememberDeviceToken(UniversityLoginRequest $request, $user): void
    {
        if (! $request->filled('fcm_token')) {
            return;
        }

        $deviceName = $request->input('device_name')
            ?: ($request->header('User-Agent') ?: 'Unknown Device');

        $user->deviceTokens()->updateOrCreate(
            ['token' => $request->input('fcm_token')],
            ['device_name' => $deviceName, 'updated_at' => now()],
        );
    }

    /** Whatever the API guard currently is, so the log reflects reality. */
    private function guardName(): string
    {
        return config('auth.guards.api.driver', 'api');
    }
}

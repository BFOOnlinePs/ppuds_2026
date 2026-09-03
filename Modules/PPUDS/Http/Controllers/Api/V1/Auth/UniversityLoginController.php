<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Validation\ValidationException;
use Modules\Core\Listeners\AuthActivitySubscriber;
use Modules\Core\Traits\ApiResponse;
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
     *     description="Signs the mobile app in against the university realm using the password grant. **The response is the realm's own JSON, passed through unchanged with the realm's own status code** — not this API's usual {status, message, data} envelope — so the app only needs to change the URL it calls; nothing else about its parsing changes. Routing the call through here is what makes the sign-in appear in the activity log; successful and failed attempts are both recorded.",
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
     *         description="Signed in. The realm's token response, verbatim.",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="access_token", type="string", example="eyJhbGciOiJSUzI1NiIsInR5cCI6..."),
     *             @OA\Property(property="expires_in", type="integer", example=300),
     *             @OA\Property(property="refresh_expires_in", type="integer", example=1800),
     *             @OA\Property(property="refresh_token", type="string", example="eyJhbGciOiJIUzUxMiIsInR5cCI6..."),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="id_token", type="string"),
     *             @OA\Property(property="scope", type="string", example="openid profile offline_access")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Rejected by the university, verbatim. A 401 is NOT always a wrong password: the realm answers a two-factor challenge with the same status and adds two_factor_required, sms_available and alternative_email. Check two_factor_required before showing a credentials error, then resend the request with auth_type and otp.",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="error", type="string", example="invalid_grant"),
     *             @OA\Property(property="error_description", type="string", example="Device not trusted. Please select OTP method."),
     *             @OA\Property(property="two_factor_required", type="boolean", example=true, description="Present when the realm wants a one-time code"),
     *             @OA\Property(property="sms_available", type="boolean", example=false),
     *             @OA\Property(property="alternative_email", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="The university accepted the credentials, but no matching local account exists or the account is blocked.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="account_not_linked"),
     *             @OA\Property(property="error_description", type="string", example="لا يوجد حساب مرتبط باسم المستخدم هذا في النظام.")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error (username or password missing)"),
     *
     *     @OA\Response(
     *         response=503,
     *         description="The university system could not be reached.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="unreachable"),
     *             @OA\Property(property="error_description", type="string")
     *         )
     *     )
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

        $accessToken = $result['data']['access_token'] ?? null;

        if (! $result['ok'] || ! $accessToken) {
            return $this->reject($username, $result);
        }

        // Maps the realm's identity onto the local account, applying the same
        // blocked-role and student-number rules the browser sign-in uses.
        try {
            $user = $action->resolveUserFromToken($accessToken);
        } catch (ValidationException $e) {
            event(new Failed($this->guardName(), null, ['username' => $username]));

            return $this->passthrough([
                'error' => 'account_not_linked',
                'error_description' => $e->validator->errors()->first(),
            ], 403);
        }

        $refreshToken = $result['data']['refresh_token'] ?? null;

        $ppuApi->storeTokenPair($accessToken, $refreshToken, $user->id, $ppuApi->mobileClientId());

        $this->rememberDeviceToken($request, $user);

        // What puts the sign-in into the activity log.
        event(new Login($this->guardName(), $user, false));

        // The realm's own response, untouched. Nothing is added or removed, so
        // the app parses it exactly as it did when it called the realm itself.
        return $this->passthrough($result['data'], 200);
    }

    /**
     * Renews the app's token pair against the university realm.
     *
     * Unauthenticated on purpose: it is called precisely when the access
     * token has expired, and the refresh token is the credential. No Login
     * event is raised — a refresh is not a new sign-in, and logging it as one
     * would bury the real sign-ins under noise. It is recorded under its own
     * activity-log event instead.
     */
    /**
     * @OA\Post(
     *     path="/api/v1/auth/university-refresh",
     *     summary="Renew the university token",
     *     description="Exchanges a refresh token for a new access token against the university realm, and keeps the pair stored on the server in step. **The realm's own JSON is passed through unchanged, with its own status code**, exactly like the sign-in endpoint. Deliberately unauthenticated: it is called once the access token has expired, and the refresh token is itself the credential. A refresh is recorded in the activity log under its own event, separate from the sign-ins.",
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
     *         description="Token renewed. The realm's token response, verbatim.",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="access_token", type="string", example="eyJhbGciOiJSUzI1NiIsInR5cCI6..."),
     *             @OA\Property(property="expires_in", type="integer", example=300),
     *             @OA\Property(property="refresh_expires_in", type="integer", example=1800),
     *             @OA\Property(property="refresh_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="scope", type="string", example="openid profile offline_access")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="The refresh token has expired or been revoked; the app must sign in again. The realm's own error, verbatim.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="invalid_grant"),
     *             @OA\Property(property="error_description", type="string", example="Token is not active")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error (refresh_token missing)"),
     *
     *     @OA\Response(
     *         response=503,
     *         description="The university system could not be reached.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="unreachable"),
     *             @OA\Property(property="error_description", type="string")
     *         )
     *     )
     * )
     */
    public function refresh(UniversityRefreshRequest $request, PpuApiService $ppuApi, AuthenticateViaKeycloakAction $action)
    {
        $result = $ppuApi->refreshPasswordGrantToken((string) $request->input('refresh_token'));

        $accessToken = $result['data']['access_token'] ?? null;

        if (! $result['ok'] || ! $accessToken) {
            if (($result['data']['error'] ?? null) === 'unreachable') {
                return $this->passthrough([
                    'error' => 'unreachable',
                    'error_description' => __('Could not reach the university system. Please try again.'),
                ], 503);
            }

            return $this->passthrough($result['data'], $result['status'] ?: 401);
        }

        $refreshToken = $result['data']['refresh_token'] ?? $request->input('refresh_token');

        $user = null;

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

        // What puts the renewal into the activity log, beside the sign-ins.
        app(AuthActivitySubscriber::class)->recordTokenRefresh($user, [
            'guard' => $this->guardName(),
        ]);

        return $this->passthrough($result['data'], 200);
    }

    /**
     * Records the failed attempt and hands the realm's own error back
     * untouched, so the app can tell a wrong password from an OTP challenge.
     */
    private function reject(string $username, array $result)
    {
        event(new Failed($this->guardName(), null, ['username' => $username]));

        // Straight through, with every field the realm sent. A rejection is
        // not always a wrong password: the realm answers an OTP challenge with
        // the same 401 plus two_factor_required / sms_available /
        // alternative_email, and the app needs all of them to decide what to
        // show next. Summarising the body here would throw that away.
        if (($result['data']['error'] ?? null) === 'unreachable') {
            return $this->passthrough([
                'error' => 'unreachable',
                'error_description' => __('Could not reach the university system. Please try again.'),
            ], 503);
        }

        return $this->passthrough($result['data'], $result['status'] ?: 401);
    }

    /**
     * Answers with the realm's own JSON shape rather than this API's usual
     * envelope. This endpoint is a proxy: the app was talking to the realm
     * directly, and keeping the contract identical means only the URL changes.
     */
    private function passthrough(array $body, int $status)
    {
        return response()->json($body, $status, [], JSON_UNESCAPED_UNICODE);
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

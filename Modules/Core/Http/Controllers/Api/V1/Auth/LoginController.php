<?php

namespace Modules\Core\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Entities\User;
use Modules\Core\Http\Requests\Auth\LoginRequest;
use Modules\Core\Traits\ApiResponse;
use Modules\Core\Transformers\V1\UserResource;
use Modules\PPUDS\Enums\LoginClient;
use Modules\PPUDS\Enums\OtpChannel;
use Modules\PPUDS\Services\LoginOtpService;
use Spatie\QueryBuilder\QueryBuilder;

class LoginController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="Login a user",
     *     description="Authenticate user credentials and return access token",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="mohamad@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret12345")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User logged in successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User logged in successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", example="1|abcdefg123456789"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Mohamad Maraqa"),
     *                     @OA\Property(property="email", type="string", example="mohamad@example.com"),
     *                     @OA\Property(property="phone", type="string", example="0569162687")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials"),
     *     @OA\Response(response=403, description="The app signs in with a one-time code only (mobile_login_mode = code): see /api/v1/auth/otp/request"),
     *     @OA\Response(response=429, description="Two-step mode: too many codes requested; data.retry_after says how many seconds to wait")
     * )
     *
     * When the account signs in with "password, then code" (its own method,
     * its role's, or the general mobile one) a correct password does not
     * return a token. It answers 200 with data.requires_code = true, the code
     * details and a login_ticket, and the app finishes with
     * POST /api/v1/auth/otp/verify {login_ticket, code}. An account that signs
     * in with a code only gets 403 — but only once its password was right.
     */
    public function login(LoginRequest $request)
    {
        $otp = app(LoginOtpService::class);

        // The dashboard decides how the app signs in.
        if (! $otp->passwordLoginAllowed(LoginClient::MOBILE)) {
            return $this->errorResponse(__('Password sign-in is turned off. Sign in with the code sent to your email.'), 403);
        }

        $loginField = $request->filled('email') ? 'email' : 'phone';

        $credentials = [
            $loginField => $request->input($loginField),
            'password'  => $request->password,
        ];

        // A person or role may have a method of their own: check the password
        // first, then follow that person's method.
        if ($otp->perUserRulesMatter(LoginClient::MOBILE)) {
            if (! Auth::validate($credentials)) {
                // validate() raises no event of its own; keep failed sign-ins in the log.
                event(new Failed(config('auth.guards.api.driver', 'api'), Auth::getLastAttempted(), [$loginField => $credentials[$loginField]]));

                return $this->errorResponse(__('The provided credentials do not match our records.'), 401);
            }

            $user = Auth::getLastAttempted();
            $mode = $otp->modeForUser($user, LoginClient::MOBILE);

            if (! $mode->allowsPassword()) {
                return $this->errorResponse(__('Your account signs in with a one-time code, not a password. Enter your email to receive one.'), 403);
            }

            if ($mode->requiresCodeAfterPassword()) {
                return $this->startTwoStepSignIn($request, $otp, $user);
            }
        }

        if (!Auth::attempt($credentials)) {
            return $this->errorResponse(__('The provided credentials do not match our records.'), 401);
        }

        $user = Auth::user();

        $user = QueryBuilder::for(User::class)
            ->where('id', $user->id)
            ->allowedIncludes(UserResource::allowedIncludes())
            ->first();

        $tokenName = $request->device_name ?: ($request->header('User-Agent') ?: 'Unknown Device');
        $token = $user->createToken($tokenName)->plainTextToken;

        if ($request->filled('fcm_token')) {
            $user->deviceTokens()->updateOrCreate(
                ['token' => $request->fcm_token],
                [
                    'device_name' => $tokenName,
                    'updated_at'  => now()
                ]
            );
        }

        return $this->successResponse([
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => new UserResource($user),
        ], __('User logged in successfully'));
    }

    /**
     * The first step of a two-step sign-in, once the password was found right
     * without signing in: a code is sent, and the app gets a short-lived login
     * ticket to finish with at /api/v1/auth/otp/verify.
     */
    private function startTwoStepSignIn(LoginRequest $request, LoginOtpService $otp, User $user)
    {
        $challenge = $otp->requestAfterPassword($user, OtpChannel::fromKey($request->input('channel')), $request->ip(), LoginClient::MOBILE);

        return $this->successResponse([
            'requires_code' => true,
            'login_ticket' => $otp->issueLoginTicket($user),
            ...$challenge->toArray(),
        ], __('Enter the code we sent you to finish signing in.'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     summary="Logout a user",
     *     description="Revokes the authenticated user's current access token and records the sign-out in the activity log. Works under both the Sanctum and Keycloak guards; under Keycloak there is no local token to revoke, but the sign-out is still recorded.",
     *     tags={"Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="User logged out successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User logged out successfully"),
     *             @OA\Property(property="data", type="string", example=null, nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        // Null under the Keycloak guard, where the token is a JWT this app
        // never issued; the sign-out below still has to be recorded.
        $user?->currentAccessToken()?->delete();

        // Neither guard raises the framework's Logout event: it belongs to the
        // session guard, while the API is stateless under both Sanctum and
        // Keycloak. Without this, sign-outs from the mobile app are invisible.
        if ($user) {
            event(new Logout(config('auth.guards.api.driver', 'api'), $user));
        }

        return $this->successResponse(null, __('User logged out successfully'));
    }
}

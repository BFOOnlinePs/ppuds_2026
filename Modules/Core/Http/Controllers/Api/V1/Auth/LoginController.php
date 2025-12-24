<?php

namespace Modules\Core\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Entities\DeviceToken;
use Modules\Core\Http\Requests\Auth\LoginRequest;
use Modules\Core\Traits\ApiResponse;
use Modules\Core\Transformers\V1\UserResource;

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
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return $this->errorResponse(__('The provided credentials do not match our records.'), 401);
        }

        $user  = Auth::user();
        $token = $user->createToken('auth-token-for-' . $user->name)->plainTextToken;

        if ($request->has('fcm_token')){
            DeviceToken::firstOrCreate([
                'token' => $request->fcm_token,
                'user_id' => $user->id,
                'device_name' => $request->device_name
            ]);
        }

        $data = [
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => new UserResource($user),
        ];

        return $this->successResponse($data, __('User logged in successfully'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     summary="Logout a user",
     *     description="Revoke the authenticated user's current access token",
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
        $request->user()?->currentAccessToken()?->delete();

        return $this->successResponse(null, __('User logged out successfully'));
    }
}

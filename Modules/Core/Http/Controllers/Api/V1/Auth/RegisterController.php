<?php

namespace Modules\Core\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Entities\DeviceToken;
use Modules\Core\Entities\User;
use Modules\Core\Http\Requests\Auth\RegisterRequest;
use Modules\Core\Traits\ApiResponse;
use Modules\Core\Transformers\V1\UserResource;

class RegisterController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Post(
     *     path="/api/v1/auth/registration",
     *     summary="Registration a new user",
     *     description="Create a new user account",
     *     security={{"sanctum": {}}},
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","phone"},
     *             @OA\Property(property="name", type="string", example="Anas"),
     *             @OA\Property(property="email", type="string", format="email", example="anas@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret12345"),
     *             @OA\Property(property="phone", type="string", example="+970599123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User registered successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Mohamad Maraqa"),
     *                 @OA\Property(property="email", type="string", example="mohamad@example.com"),
     *                 @OA\Property(property="phone", type="string", example="0569162687"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-09-28T14:30:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=500, description="User registration failed")
     * )
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => bcrypt($request->password),
            'phone'    => $request->phone,
        ]);

        if ($request->has('fcm_token')){
            DeviceToken::create([
                'token' => $request->fcm_token,
                'user_id' => $user->id,
                'device_name' => $request->device_name
            ]);
        }

        $user->generateAvatar();

        $role = $request->input('role', 'Customer');

        $user->assignRole($role);

        if ($user) {
            return $this->successResponse(
                new UserResource($user),
                __('User registered successfully')
            );
        }

        return $this->errorResponse(__('User registration failed'), 500);
    }
}

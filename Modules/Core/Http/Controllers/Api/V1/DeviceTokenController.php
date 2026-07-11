<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Actions\StoreDeviceTokenAction;
use Modules\Core\Http\Requests\DeviceTokenRequest;
use Modules\Core\Traits\ApiResponse;

class DeviceTokenController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Post(
     *     path="/api/v1/device-tokens",
     *     summary="Register or refresh an FCM device token",
     *     description="Stores the authenticated user's FCM token so they can receive push notifications.",
     *     tags={"Device Tokens"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fcm_token"},
     *             @OA\Property(property="fcm_token", type="string", example="fGhI...token"),
     *             @OA\Property(property="device_name", type="string", example="android")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Device token stored successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(DeviceTokenRequest $request, StoreDeviceTokenAction $storeDeviceToken)
    {
        $storeDeviceToken->execute(
            $request->user(),
            $request->input('fcm_token'),
            $request->input('device_name') ?: ($request->userAgent() ?: 'Unknown Device'),
        );

        return $this->successResponse(null, __('Device token stored successfully'));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/device-tokens",
     *     summary="Remove an FCM device token",
     *     description="Deletes the given FCM token for the authenticated user (e.g. on logout).",
     *     tags={"Device Tokens"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"fcm_token"},
     *             @OA\Property(property="fcm_token", type="string", example="fGhI...token")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Device token removed successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(DeviceTokenRequest $request)
    {
        $request->user()
            ->deviceTokens()
            ->where('token', trim((string) $request->input('fcm_token')))
            ->delete();

        return $this->successResponse(null, __('Device token removed successfully'));
    }
}

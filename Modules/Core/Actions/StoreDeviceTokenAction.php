<?php

namespace Modules\Core\Actions;

use Modules\Core\Entities\DeviceToken;
use Modules\Core\Entities\User;

class StoreDeviceTokenAction
{
    /**
     * Store (or refresh) an FCM device token for the given user.
     *
     * Returns null when no usable token was provided.
     */
    public function execute(User $user, ?string $token, ?string $deviceName = null): ?DeviceToken
    {
        $token = is_string($token) ? trim($token) : '';

        if ($token === '') {
            return null;
        }

        $deviceName = is_string($deviceName) && trim($deviceName) !== ''
            ? trim($deviceName)
            : 'Unknown Device';

        return $user->deviceTokens()->updateOrCreate(
            ['token' => $token],
            [
                'device_name' => $deviceName,
                'updated_at'  => now(),
            ]
        );
    }
}

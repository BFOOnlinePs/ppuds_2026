<?php

namespace Modules\PPUDS\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;

class KeycloakUserRepository
{
    /**
     * @param  object  $token        كائن التوكن الذي تم فك تشفيره بواسطة البكج
     * @param  array   $credentials  مصفوفة الإعدادات من ملف config
     * @return User|null
     */
    public function retrieveByToken(object $token, array $credentials): ?User
    {
        // 1. استخراج الإيميل بشكل آمن وبدون تحويل الكائن لمصفوفة (Clean Code)
        $email = $token->email ?? (($token->preferred_username ?? '') . '@ppu.edu');

        if (empty($email)) {
            return null; // رفض الطلب فوراً إذا كان التوكن تالفاً أو ينقصه الإيميل
        }

        $user = User::where('email', $email)->first();

        $clientRoles = $token->resource_access->{'Dual-Studies-Laravel'}->roles ?? [];

        if (! $user) {
            return DB::transaction(function () use ($email, $token, $clientRoles) {
                $newUser = User::create([
                    'name'     => $token->name ?? $token->preferred_username ?? 'Student',
                    'email'    => $email,
                    'password' => Hash::make(Str::random(32)),
                ]);

                $newUser->syncRoles($clientRoles);

                if ($newUser->hasRole('DualStudiesStudent') || in_array('Student', $clientRoles)) {
                    $newUser->studentProfile()->create([
                        'student_number' => explode('@', $email)[0]
                    ]);
                }

                return $newUser;
            });
        }

        $cacheKey = "user_{$user->id}_keycloak_roles";
        $rolesHash = md5(json_encode($clientRoles));

        if (Cache::get($cacheKey) !== $rolesHash) {
            $user->syncRoles($clientRoles);
            Cache::put($cacheKey, $rolesHash, now()->addHours(2)); // حفظ البصمة
        }

        return $user;
    }
}

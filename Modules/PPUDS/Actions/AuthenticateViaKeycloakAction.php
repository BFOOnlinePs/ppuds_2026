<?php

namespace Modules\PPUDS\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as KeycloakUser;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\StudentProfile;

class AuthenticateViaKeycloakAction
{
    public function execute(KeycloakUser $keycloakUser): User
    {
        $accessToken = $keycloakUser->token;
        $tokenParts = explode('.', $accessToken);

        if (isset($tokenParts[1])) {
            $payload = json_decode(base64_decode($tokenParts[1]), true);

            // أضف هذه الأسطر لعمل التست والتصوير
            dd([
                'Message' => 'مرحباً جهاد، هذه هي الصلاحيات المستخرجة من التوكن:',
                'Realm_Roles' => $payload['realm_access']['roles'] ?? 'لا يوجد صلاحيات عامة',
                'Laravel_App_Roles' => $payload['resource_access']['laravel-app']['roles'] ?? 'لا يوجد صلاحيات خاصة بالتطبيق',
                'Full_Payload' => $payload, // لعرض كامل البيانات إذا لزم الأمر
            ]);
        }
        dd($keycloakUser);

        return DB::transaction(function () use ($keycloakUser) {

            // 1. استخراج البيانات الأساسية من كائن Keycloak
            $email = $keycloakUser->getEmail();
            $name = $keycloakUser->getName();

            // الوصول للمصفوفة الداخلية (user) التي تحتوي على تفاصيل إضافية كما ظهر في الصورة
            $rawUser = $keycloakUser->user;

            // 2. إنشاء أو تحديث حساب المستخدم في جدول (users) الخاص بـ Core
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    // يمكننا حفظ الاسم باللغة الإنجليزية إذا كان متوفراً في بيانات الجامعة
                    'name_en' => trim(($rawUser['given_name'] ?? '').' '.($rawUser['family_name'] ?? '')),
                    'password' => bcrypt(Str::random(24)), // توليد كلمة مرور عشوائية ومعقدة
                ]
            );

            $studentNumber = explode('@', $email)[0];

            StudentProfile::updateOrCreate(
                ['user_id' => $user->id], // مفتاح الربط مع جدول المستخدمين
                [
                    'student_number' => $studentNumber,
                ]
            );

            // 5. تعيين صلاحية "طالب" للمستخدم (بما أنك تستخدم حزمة Spatie HasRoles)
            // هذه الخطوة تضمن أن المستخدم الجديد سيحصل على الصلاحيات الصحيحة فور دخوله
            if (! $user->hasRole('Student')) {
                // تأكد من أن دور 'Student' موجود مسبقاً في جدول أدوار النظام
                $user->assignRole('Student');
            }

            // 6. تسجيل دخول المستخدم فعلياً في نظام لارافيل
            Auth::login($user);

            return $user;
        });
    }
}

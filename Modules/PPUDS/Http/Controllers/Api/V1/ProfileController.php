<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\Core\Transformers\V1\UserResource;
use Modules\Core\Entities\User; // أضفنا استدعاء الموديل
use Spatie\QueryBuilder\QueryBuilder;

class ProfileController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/me",
     * summary="جلب بيانات المستخدم الحالي",
     * description="يستخدم هذا الرابط لجلب بيانات المستخدم صاحب التوكن من Keycloak ومزامنة صلاحياته",
     * tags={"PPUDS - Profile"},
     * security={{"bearerAuth": {}}},
     * @OA\Response(
     * response=200,
     * description="تم جلب البيانات بنجاح",
     * @OA\JsonContent(ref="#/components/schemas/UserResource")
     * ),
     * @OA\Response(response=401, description="التوكن غير صحيح أو منتهي")
     * )
     */
    public function show(Request $request)
    {
        // التصحيح: نمرر Builder يبدأ من الـ ID الخاص بالمستخدم الحالي
        $userQuery = User::where('id', $request->user()->id);

        $user = QueryBuilder::for($userQuery)
                    ->allowedFields(UserResource::allowedFields())

            ->allowedIncludes(UserResource::allowedIncludes())
            ->first(); // جلب الكائن بعد تطبيق الفلاتر والـ includes

        // تحميل العلاقات الأساسية لضمان ظهورها في الـ Resource
        $user->loadMissing(['roles', 'studentProfile', 'media']);

        return $this->successResponse(
            new UserResource($user),
            __('Profile retrieved successfully')
        );
    }
}

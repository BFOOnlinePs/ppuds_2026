<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\Core\Transformers\V1\UserResource;
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
        // نستخدم الـ QueryBuilder للسماح للمبرمج بطلب الـ includes مثل (roles, studentProfile)
        $user = QueryBuilder::for($request->user())
            ->allowedIncludes(UserResource::allowedIncludes())
            ->allowedFields(UserResource::allowedFields())
            // نضمن تحميل البيانات الأساسية التي يحتاجها الفلاتر فوراً
            ->load(['roles', 'studentProfile', 'media']);

        return $this->successResponse(
            new UserResource($user),
            __('Profile retrieved successfully')
        );
    }
}

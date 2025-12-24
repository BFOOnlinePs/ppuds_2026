<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Entities\User;
use Modules\Core\Transformers\V1\UserResource;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Product;
use Modules\Items\Transformers\V1\AddonOptionResource;
use Modules\Items\Transformers\V1\AddonResource;
use Modules\Items\Transformers\V1\CategoryResource;
use Modules\Items\Transformers\V1\OfferResource;
use Modules\Items\Transformers\V1\ProductResource;
use Spatie\Permission\Models\Role;

class SyncController extends Controller
{
    public function syncUsers(Request $request)
    {
        $since = $request->input('since', '1970-01-01 00:00:00');

        $users = User::with('media', 'roles')->where('updated_at' , '>' , $since)->get();

        return UserResource::collection($users)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    public function syncRoles(Request $request)
    {
        $since = $request->input('since', '1970-01-01 00:00:00');

        $roles = Role::with('permissions')
            ->where('updated_at', '>', $since)
            ->get();

        $data = $roles->map(function($role) {
            return [
                'id'          => $role->id,
                'name'        => $role->name,       // اسم الدور (مثلاً: kitchen)
                'permissions' => $role->permissions->pluck('name'), // مصفوفة الصلاحيات: ['view_orders', 'finish_cooking']
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'timestamp' => now()->toDateTimeString(),
            ]
        ]);
    }
}

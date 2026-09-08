<?php

namespace Modules\Branch\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Transformers\V1\BranchResource;
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

class SyncController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/v1/branches/sync/branches",
     * summary="Delta sync of branches",
     * description="مزامنة تفاضلية للتطبيقات التي تحتفظ بنسخة محلية. أرسل since بآخر قيمة استلمتها في meta.timestamp، فتعود الفروع المعدَّلة بعدها فقط. أول مزامنة: اترك since فارغاً لتحصل على كل شيء. احفظ meta.timestamp العائد لاستخدامه في الطلب التالي.",
     * tags={"Sync"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(name="since", in="query", required=false, description="التاريخ والوقت بصيغة Y-m-d H:i:s. الافتراضي 1970-01-01 00:00:00 أي كل السجلات", @OA\Schema(type="string", example="2026-09-01 12:00:00")),
     *
     * @OA\Response(
     * response=200,
     * description="Branches updated since the given timestamp",
     *
     * @OA\JsonContent(
     * @OA\Property(property="data", type="array", @OA\Items(type="object")),
     * @OA\Property(property="meta", type="object", @OA\Property(property="timestamp", type="string", example="2026-09-08 10:30:00", description="مرّره كـ since في المزامنة القادمة"))
     * )
     * ),
     *
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function syncBranches(Request $request)
    {
        $since = $request->input('since', '1970-01-01 00:00:00');

        $branches = Branch::with('translations')->where('updated_at' , '>' , $since)->get();

        return BranchResource::collection($branches)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }
}

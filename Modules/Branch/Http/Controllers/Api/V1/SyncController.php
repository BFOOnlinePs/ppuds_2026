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

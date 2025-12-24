<?php

namespace Modules\Items\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Transformers\V1\AddonOptionResource;
use Modules\Items\Transformers\V1\AddonResource;
use Modules\Items\Transformers\V1\CategoryResource;
use Modules\Items\Transformers\V1\OfferResource;
use Modules\Items\Transformers\V1\OrderResource;
use Modules\Items\Transformers\V1\ProductResource;

class SyncController extends Controller
{
    public function syncProducts(Request $request)
    {
        $since = $request->input('since', '1970-01-01 00:00:00');

        $products = Product::with(['translations' ,'categories' , 'addons', 'addons.addonOptions'])->where('updated_at' , '>' , $since)->get();

        return ProductResource::collection($products)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    public function syncCategories(Request $request)
    {
        $since = $request->input('since', '1970-01-01 00:00:00');

        $categories = Category::with('translations')->where('updated_at' , '>' , $since)->get();

        return CategoryResource::collection($categories)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    public function syncAddons(Request $request){
        $since = $request->input('since', '1970-01-01 00:00:00');

        $addons = Addon::where('updated_at' , '>' , $since)->get();

        return AddonResource::collection($addons)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    public function syncAddonsOptions(Request $request){
        $since = $request->input('since', '1970-01-01 00:00:00');

        $addonsOptions = AddonOption::where('updated_at' , '>' , $since)->get();

        return AddonOptionResource::collection($addonsOptions)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    public function syncOffers(Request $request){
        $since = $request->input('since', '1970-01-01 00:00:00');

        $offers = Offer::where('updated_at' , '>' , $since)->get();

        return OfferResource::collection($offers)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    public function syncOrders(Request $request){
        $since = $request->input('since', '1970-01-01 00:00:00');

        $orders = Order::with('items')->where('updated_at' , '>' , $since)->get();

        return OrderResource::collection($orders)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }
}

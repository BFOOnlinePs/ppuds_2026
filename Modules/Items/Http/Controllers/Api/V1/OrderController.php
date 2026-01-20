<?php

namespace Modules\Items\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Core\Events\RefreshNotificationEvent;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Core\Traits\ApiResponse;
use Modules\Coupon\Services\CouponService;
use Modules\Delivery\Entities\CustomerAddress;
use Modules\Delivery\Services\DeliveryFeeCalculatorService;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;
use Modules\Items\Http\Requests\OrderRequest;
use Modules\Items\Http\Requests\UpdateOrderStatusRequest;
use Modules\Items\Transformers\V1\OrderResource;
use Spatie\QueryBuilder\QueryBuilder;

class OrderController extends Controller
{
    use ApiResponse;

    protected $couponService;


    public function __construct(CouponService $couponService)
    {
        $this->couponService = $couponService;
    }

    /**
     * @OA\Get(
     * path="/api/v1/items/orders",
     * summary="Get all orders",
     * description="Retrieve a list of orders for the authenticated user, with support for filtering, sorting, and pagination.",
     * tags={"Orders"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(
     * type="string",
     * default="ar",
     * example="en"
     * )
     * ),
     * @OA\Parameter(
     *  name="filter[status]",
     *  in="query",
     *  required=false,
     *  description="Filter orders by status ID (e.g., 1 for Pending, 2 for Confirmed)",
     *  @OA\Schema(
     *  type="integer",
     *  enum={1, 2, 3, 4, 5, 6},
     *  example=1
     *  )
     *  ),
     *  @OA\Parameter(
     *  name="filter[user_id]",
     *  in="query",
     *  required=false,
     *  description="Filter orders by user ID",
     *  @OA\Schema(
     *  type="integer",
     *  example=1
     *  )
     *  ),
     *      @OA\Property(
     *  property="payment_method",
     *  type="integer",
     *  description="طريقة الدفع: 1 = الدفع عند الاستلام, 2 = بطاقة ائتمانية, 3 = باي بال",
     *  enum={1, 2, 3},
     *  example=1
     *  ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources. e.g., 'user', 'items', 'items.product'",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * required=false,
     * description="Sort by a field. e.g., 'created_at' for oldest, '-created_at' for newest.",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * description="Number of items per page.",
     * @OA\Schema(type="integer", example=15)
     * ),
     * @OA\Parameter(
     * name="page",
     * in="query",
     * required=false,
     * description="Page number.",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Orders retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Orders retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=101),
     * @OA\Property(property="order_number", type="string", example="ORD-ABC12345"),
     * @OA\Property(property="total", type="number", format="float", example=250.50),
     * @OA\Property(property="status", type="string", example="قيد التحضير"),
     * @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-12 14:02:00")
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */

    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page');
        $maxPerPage = config('core.pagination.max_per_page');
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $ordersQuery = QueryBuilder::for(Order::class)
            ->allowedFields(OrderResource::allowedFields())
            ->allowedFilters(OrderResource::allowedFilters())
            ->allowedSorts(OrderResource::allowedSorts())
            ->allowedIncludes(OrderResource::allowedIncludes())
            ->where(function ($query) {
                $query->where('payment_method', PaymentMethod::CREDIT_CARD->value)
                    ->where('payment_status', PaymentStatus::PAID->value);
            })
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            OrderResource::collection($ordersQuery),
            __('Orders retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/items/orders",
     * summary="Create a new order",
     * description="Create a new order for the authenticated user, with optional add-ons for each item.",
     * tags={"Orders"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"delivery_address", "contact_phone", "payment_method", "items"},
     * @OA\Property(property="delivery_address", type="string", example="123 Main St, Ramallah"),
     * @OA\Property(property="contact_phone", type="string", example="+970599123456"),
     * @OA\Property(property="payment_method", type="string", example="cash_on_delivery"),
     * @OA\Property(property="notes", type="string", example="Please call before arriving."),
     * @OA\Property(
     * property="items",
     * type="array",
     * @OA\Items(
     * type="object",
     * required={"product_id", "quantity"},
     * @OA\Property(property="product_id", type="integer", example=33),
     * @OA\Property(property="quantity", type="integer", example=1),
     *
     *
     * @OA\Property(
     * property="addons",
     * type="array",
     * description="An optional array of add-ons for this specific item.",
     * @OA\Items(
     * type="object",
     * required={"addon_id", "quantity"},
     * @OA\Property(property="addon_id", type="integer", example=5, description="ID of the addon"),
     * @OA\Property(property="quantity", type="integer", example=2, description="Quantity of the addon")
     * )
     * ),
     *
     *
     * )
     * )
     * )
     * ),
     * @OA\Response(response=201, description="Order created successfully"),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(OrderRequest $request)
    {
        $validatedData = $request->validated();
        $itemsData = $validatedData['items'];
        $tablePrefix = config('items.table_prefix');

        // 💡 1. تحميل المنتجات مع العروض الفعالة (activeOffers) لتجنب N+1
        $productIds = collect($itemsData)->pluck('product_id');

        // تأكد أنك أضفت دالة activeOffers في موديل Product كما شرحت لك سابقاً
        $products = Product::with(['parent', 'activeOffers'])->findMany($productIds)->keyBy('id');

        // تحميل خيارات الإضافات
        $addonOptionIds = collect($itemsData)->pluck('addons.*.addon_option_id')->flatten()->unique();
        $addonOptions = AddonOption::findMany($addonOptionIds)->keyBy('id');

        // تحميل الأسعار المخصصة
        $parentProductIds = $products->pluck('parent_id')->filter()->unique();
        $allProductIdsForLookup = $productIds->merge($parentProductIds)->unique();
        $customPricesMap = $this->preloadCustomPrices($allProductIdsForLookup, $addonOptionIds, $tablePrefix);

        $subTotal = 0; // مجموع السلة قبل خصم الكوبون (لكن بعد خصم عروض المنتجات)
        $itemsToCreate = [];

        // مصفوفة خاصة لتمريرها لسرفيس الكوبون
        $itemsForCoupon = [];

        // --- 🔄 الحلقة الأولى: تجهيز الحسابات والمنتجات ---
        foreach ($itemsData as $item) {
            $product = $products->get($item['product_id']);
            if (!$product) continue;

            // أ. حساب سعر المنتج الأساسي (مع مراعاة عروض المنتجات)
            $baseProductPrice = $product->sale_price ?? $product->base_price;
            $finalProductPrice = $baseProductPrice;
            $offerDiscountPerUnit = 0;

            // 🔥 فحص وجود عرض فعال على المنتج
            $activeOffer = $product->activeOffers->first();

            if ($activeOffer) {
                if ($activeOffer->type->value === 'percentage') {
                    $discountVal = ($baseProductPrice * $activeOffer->value) / 100;
                    $finalProductPrice = $baseProductPrice - $discountVal;
                } elseif ($activeOffer->type->value === 'fixed') {
                    $discountVal = $activeOffer->value;
                    $finalProductPrice = max(0, $baseProductPrice - $discountVal);
                }
                $offerDiscountPerUnit = $baseProductPrice - $finalProductPrice;
            }

            // تجهيز نسخة للكوبون بالسعر النهائي للمنتج
            $itemForCouponModel = clone $product;
            // نفترض أن الكوبون يقرأ دالة getDiscountablePrice() أو attribute معين
            // يمكنك تعديل منطق الكوبون ليقرأ هذا السعر المعدل
            $itemForCouponModel->forceFill(['price' => $finalProductPrice]);

            $itemsForCoupon[] = (object) [
                'model' => $itemForCouponModel,
                'qty'   => $item['quantity']
            ];

            // ب. حساب الإضافات (Addons)
            $itemTotalPrice = $finalProductPrice * $item['quantity'];
            $optionsToAttach = [];

            if (!empty($item['addons'])) {
                $productIdForAddonLookup = $product->parent_id ?? $product->id;
                foreach ($item['addons'] as $addonData) {
                    $optionId = $addonData['addon_option_id'];
                    $addonOption = $addonOptions->get($optionId);
                    if (!$addonOption) continue;

                    $finalAddonPrice = $customPricesMap[$productIdForAddonLookup][$optionId] ?? (float) $addonOption->price;
                    $addonTotalCost = $finalAddonPrice * $addonData['quantity'];

                    $subTotal += $addonTotalCost;
                    $itemTotalPrice += $addonTotalCost;

                    $optionsToAttach[$optionId] = [
                        'quantity' => $addonData['quantity'],
                        'price'    => $finalAddonPrice
                    ];
                }
            }

            $subTotal += $finalProductPrice * $item['quantity'];

            // تخزين البيانات لإنشائها لاحقاً
            $itemsToCreate[] = [
                'product_id'      => $product->id,
                'quantity'        => $item['quantity'],
                'price'           => $finalProductPrice,    // السعر بعد عرض المنتج
                'original_price'  => $baseProductPrice,     // السعر قبل عرض المنتج
                'offer_discount'  => $offerDiscountPerUnit, // قيمة الخصم للقطعة
                'total_price'     => $itemTotalPrice,
                'notes'           => $item['notes'] ?? null,
                'options_to_attach' => $optionsToAttach,
            ];
        }

        // --- ✂️ حساب خصم الكوبون (على مستوى السلة) ---
        $discountAmount = 0;
        $couponCode = null;

        if ($request->filled('coupon_code')) {
            try {
                // نمرر المنتجات (بأسعارها المعدلة) للسرفيس
                $couponResult = $this->couponService->apply($request->coupon_code, $itemsForCoupon);

                $discountAmount = $couponResult['discount_amount'];
                $couponCode = $couponResult['code'];
            } catch (\Exception $e) {
                return $this->errorResponse($e->getMessage(), 422);
            }
        }

        // --- 💾 بدء عملية الحفظ (Transaction) ---
        $order = null;

        try {
            DB::transaction(function () use ($validatedData, $subTotal, $itemsToCreate, &$order, $discountAmount, $couponCode) {

                // 1. حساب رسوم التوصيل
                $customerAddress = CustomerAddress::find($validatedData['delivery_address']);

                // استقبال النتيجة (سواء كانت مصفوفة أو رقم، نأخذ القيمة الصحيحة)
                $deliveryCalculation = app(DeliveryFeeCalculatorService::class)->calculate(
                    customerLat: $customerAddress ? (float) $customerAddress->latitude : 0.0,
                    customerLng: $customerAddress ? (float) $customerAddress->longitude : 0.0,
                    branchId: $validatedData['branch_id']
                );

                // إذا كان السرفيس يرجع مصفوفة نأخذ المفتاح، وإذا رقم نأخذه مباشرة
                $deliveryFee = is_array($deliveryCalculation)
                    ? (float) $deliveryCalculation['total_fee']
                    : (float) $deliveryCalculation;


                // 🔥 2. تطبيق المعادلات النهائية
                // SubTotal النهائي = (سعر المنتجات - خصم الكوبون)
                $subTotalAfterDiscount = max(0, $subTotal - $discountAmount);

                // Total النهائي = (SubTotal النهائي + التوصيل)
                $grandTotal = $subTotalAfterDiscount + $deliveryFee;

                // 3. إنشاء الطلب
                $order = Order::create([
                    'user_id'          => auth()->id(),
                    'order_number'     => $this->generateOrderNumber(),
                    'delivery_address' => $validatedData['delivery_address'],
                    'branch_id'        => $validatedData['branch_id'],
                    'contact_phone'    => is_array($validatedData['contact_phone']) ? implode('', $validatedData['contact_phone']) : $validatedData['contact_phone'],
                    'payment_method'   => $validatedData['payment_method'],
                    'notes'            => $validatedData['notes'] ?? null,
                    'delivery_type'    => $validatedData['delivery_type'] ?? 'delivery',

                    // القيم المالية
                    'sub_total'        => $subTotalAfterDiscount,
                    'delivery_fee'     => $deliveryFee,
                    'discount_amount'  => $discountAmount,
                    'coupon_code'      => $couponCode,
                    'total'            => $grandTotal,

                    'status'           => OrderStatus::PENDING,
                    'payment_status'   => PaymentStatus::UNPAID,
                    'created_by'       => auth()->id()
                ]);

                // 4. إنشاء عناصر الطلب
                foreach ($itemsToCreate as $itemData) {
                    $orderItem = $order->items()->create([
                        'product_id'     => $itemData['product_id'],
                        'quantity'       => $itemData['quantity'],
                        'price'          => $itemData['price'],
                        'total_price'    => $itemData['total_price'],
                        'notes'          => $itemData['notes'],

                        // حفظ تفاصيل العرض (تأكد أن هذه الأعمدة موجودة في الداتابيس)
                        'original_price' => $itemData['original_price'],
                        'offer_discount' => $itemData['offer_discount'],
                    ]);

                    if (!empty($itemData['options_to_attach'])) {
                        $orderItem->addonOptions()->attach($itemData['options_to_attach']);
                    }
                }

                // 5. تسجيل استخدام الكوبون
                if ($couponCode) {
                    $this->couponService->logUsage(
                        $couponCode,
                        $order->id,
                        $discountAmount,
                        auth()->id()
                    );
                }
            });
        } catch (\Exception $e) {
            // إرجاع تفاصيل الخطأ للديباج
            return response()->json([
                'status' => false,
                'message' => 'Order creation failed',
                'error_detail' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }

        // --- ✅ الرد النهائي ---
        // تحميل العلاقات للريسورس
        $order->load(['user', 'items.product', 'items.addonOptions', 'deliveryAddress']);

        if ($order->user){
            $order->user->notify(new GeneralNotification(
                title: __('New Order Created'),
                message: __('Your order #:order_number has been created successfully.', ['order_number' => $order->order_number]),
            ));
        }

        return $this->successResponse(
            new OrderResource($order),
            __('Order created successfully'),
            201
        );
    }

    /**
     * دالة مساعدة خاصة (Private) لتحميل الأسعار المخصصة مسبقاً
     */
    private function preloadCustomPrices($productIds, $addonOptionIds, $tablePrefix): array
    {
        $customizations = DB::table("{$tablePrefix}addon_product_option AS apo")
            ->select('apo.price', 'apo.addon_option_id', 'ap.product_id')
            ->join("{$tablePrefix}addon_product AS ap", 'ap.id', '=', 'apo.addon_product_id')
            ->whereIn('ap.product_id', $productIds)
            ->whereIn('apo.addon_option_id', $addonOptionIds)
            ->whereNotNull('apo.price')
            ->get();

        $map = [];
        foreach ($customizations as $c) {
            $map[$c->product_id][$c->addon_option_id] = (float) $c->price;
        }
        return $map;
    }

    /**
     * دالة مساعدة لإنشاء رقم الطلب (للحفاظ على نظافة دالة store)
     */
    private function generateOrderNumber(): string
    {
        $lastOrder = Order::orderBy('id', 'desc')->first();
        $order_id_to_use = ($lastOrder ? $lastOrder->id : 0) + 1;
        $now = now();
        $datePart = $now->format('ydm');
        $timePart = $now->format('Hi');
        return $datePart . $timePart . $order_id_to_use;
    }

    /**
     * @OA\Get(
     * path="/api/v1/items/orders/{order}",
     * summary="Get a single order",
     * description="Retrieve details of a specific order by ID. Users can only view their own orders.",
     * tags={"Orders"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="order", in="path", required=true, description="The ID of the order", @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Order retrieved successfully"),
     * @OA\Response(response=403, description="Forbidden/Access Denied"),
     * @OA\Response(response=404, description="Order not found")
     * )
     */
    public function show(Order $order)
    {
        if (auth()->id() !== $order->user_id) {
            return $this->errorResponse(__('Access Denied'), 403);
        }

        $order->load(['user', 'items.product', 'items.addonOptions']);

        return $this->successResponse(
            new OrderResource($order),
            __('Order retrieved successfully')
        );
    }

    /**
     * @OA\Patch(
     * path="/api/v1/items/orders/{order}/status",
     * summary="Update order status",
     * description="Change the status of an order. Logic validation is handled via Request.",
     * tags={"Orders"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="order", in="path", required=true, description="Order ID", @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"status"},
     * @OA\Property(property="status", type="integer", description="New status ID", example=4),
     * @OA\Property(property="note", type="string", description="Optional note", example="Driver assigned")
     * )
     * ),
     * @OA\Response(response=200, description="Status updated successfully"),
     * @OA\Response(response=422, description="Invalid status transition")
     * )
     */
    public function updateOrderStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        try {
            DB::transaction(function () use ($request, $order) {
                $newStatus = OrderStatus::from((int) $request->status);
                $oldStatus = $order->status;

                $order->update([
                    'status' => $newStatus
                ]);

                if ($order->user_id && $oldStatus != $newStatus) {
                    $order->user->notify(new GeneralNotification(
                        title: __('Order Status Updated'),
                        message: __('Your order #:order_number status is now :status', [
                            'order_number' => $order->order_number,
                            'status'       => $newStatus->getLabel()
                        ]),
                        icon: 'bell',
                        color: 'text-primary',
                    ));
                }
            });
        } catch (\Exception $e) {
            // تسجيل الخطأ داخلياً
            report($e);
            return $this->errorResponse($e->getMessage(), 500);
        }

        return $this->successResponse(
            new OrderResource($order->fresh()),
            __('Order status updated successfully')
        );
    }
}

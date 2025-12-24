<?php

namespace Modules\Delivery\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\Delivery\Entities\CustomerAddress; // تأكد من أن الموديل هنا (أو في Modules\Customer\Entities)
use Modules\Delivery\Http\Requests\CustomerAddressesRequest;
use Modules\Delivery\Services\DeliveryFeeCalculatorService;
use Modules\Delivery\Transformers\V1\CustomerAdressesResource; // لاحظ الاسم CustomerAdressesResource
use Modules\Delivery\Transformers\V1\DeliveryPricingResource;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Customer Addresses
 *
 * API endpoints for managing customer addresses.
 */
class CustomerAddressesController extends Controller
{
    use ApiResponse;

    protected $calculatorService;

    public function __construct(DeliveryFeeCalculatorService $calculatorService)
    {
        $this->calculatorService = $calculatorService;
    }

    /**
     * Get a paginated list of customer addresses.
     *
     * @OA\Get(
     * path="/api/v1/delivery/customer-addresses",
     * summary="Get customer addresses list",
     * tags={"Customer Addresses"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=false,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\Parameter(name="include", in="query", required=false, @OA\Schema(type="string", example="user"), description="Include relationships (e.g., user)"),
     * @OA\Parameter(name="filter[user_id]", in="query", required=false, @OA\Schema(type="integer", example=1), description="Filter by user ID"),
     * @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=10)),
     * @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", example=1)),
     * @OA\Response(
     * response=200,
     * description="Customer addresses retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Customer addresses retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="user_id", type="integer", example=1),
     * @OA\Property(property="label", type="string", example="Home"),
     * @OA\Property(property="latitude", type="number", format="float", example=31.50000000),
     * @OA\Property(property="longitude", type="number", format="float", example=34.46667000),
     * @OA\Property(property="address_details", type="string", example="Building 1, Floor 2, Apt 5"),
     * @OA\Property(property="delivery_instructions", type="string", example="Call upon arrival"),
     * @OA\Property(property="is_default", type="boolean", example=true)
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 15);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min((int) request('per_page', $defaultPerPage), $maxPerPage);

        $customerAddresses = QueryBuilder::for(CustomerAddress::class)
        ->allowedFields(CustomerAdressesResource::allowedFields())
            ->allowedFilters(CustomerAdressesResource::allowedFilters())
            ->allowedSorts(CustomerAdressesResource::allowedSorts())
            ->allowedIncludes(CustomerAdressesResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            CustomerAdressesResource::collection($customerAddresses),
            __('Customer addresses retrieved successfully')
        );
    }

    /**
     * Store a new customer address.
     *
     * @OA\Post(
     * path="/api/v1/delivery/customer-addresses",
     * summary="Create a new customer address",
     * tags={"Customer Addresses"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=false,
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\RequestBody(
     * required=true,
     * description="Customer address data",
     * @OA\JsonContent(
     * required={"label", "latitude", "longitude", "address_details"},
     * @OA\Property(property="label", type="string", example="Work"),
     * @OA\Property(property="latitude", type="number", format="float", example=31.51234567),
     * @OA\Property(property="longitude", type="number", format="float", example=34.45678901),
     * @OA\Property(property="address_details", type="string", example="Tech Park, Building 3"),
     * @OA\Property(property="delivery_instructions", type="string", example="Leave with reception"),
     * @OA\Property(property="is_default", type="boolean", example=false)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Customer address created successfully",
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Customer address created successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=2),
     * @OA\Property(property="user_id", type="integer", example=1),
     * @OA\Property(property="label", type="string", example="Work"),
     * @OA\Property(property="latitude", type="number", format="float", example=31.51234567),
     * @OA\Property(property="longitude", type="number", format="float", example=34.45678901),
     * @OA\Property(property="address_details", type="string", example="Tech Park, Building 3"),
     * @OA\Property(property="delivery_instructions", type="string", example="Leave with reception"),
     * @OA\Property(property="is_default", type="boolean", example=false)
     * )
     * )
     * ),
     * @OA\Response(response=422, description="Validation error"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(CustomerAddressesRequest $request)
    {
        $validatedData = $request->validated();

        $validatedData['user_id'] = auth()->id();

        if ($request->input('is_default') == true) {
            CustomerAddress::where('user_id', auth()->id())->update(['is_default' => false]);
        }

        $customerAddress = CustomerAddress::create($validatedData);

        return $this->successResponse(
            new CustomerAdressesResource($customerAddress),
            __('Customer address created successfully')
        );
    }

    /**
     * Get a single customer address.
     *
     * @OA\Get(
     * path="/api/v1/delivery/customer-addresses/{customerAdresses}",
     * summary="Get a single customer address",
     * tags={"Customer Addresses"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(name="include", in="query", required=false, @OA\Schema(type="string", example="user")),
     * @OA\Response(
     * response=200,
     * description="Customer address retrieved successfully",
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Customer address retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="user_id", type="integer", example=1),
     * @OA\Property(property="label", type="string", example="Home"),
     * @OA\Property(property="latitude", type="number", format="float", example=31.50000000),
     * @OA\Property(property="longitude", type="number", format="float", example=34.46667000),
     * @OA\Property(property="address_details", type="string", example="Building 1, Floor 2, Apt 5"),
     * @OA\Property(property="delivery_instructions", type="string", example="Call upon arrival"),
     * @OA\Property(property="is_default", type="boolean", example=true)
     * )
     * )
     * ),
     * @OA\Response(response=404, description="Customer address not found"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(CustomerAddress $customerAddress)
    {
        if ($customerAddress->user_id !== auth()->id()) {
            return $this->errorResponse(__('This action is unauthorized.'), 403);
        }

        $customerAddress->load(request('include', []));

        return $this->successResponse(
            new CustomerAdressesResource($customerAddress),
            __('Customer address retrieved successfully')
        );
    }

    /**
     * Update an existing customer address.
     *
     * @OA\Put(
     * path="/api/v1/delivery/customer-addresses/{customerAddresses}",
     * summary="Update an existing customer address",
     * tags={"Customer Addresses"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=false,
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\RequestBody(
     * required=true,
     * description="Customer address data",
     * @OA\JsonContent(
     * required={"label", "latitude", "longitude", "address_details"},
     * @OA\Property(property="label", type="string", example="Work Updated"),
     * @OA\Property(property="latitude", type="number", format="float", example=31.51234567),
     * @OA\Property(property="longitude", type="number", format="float", example=34.45678901),
     * @OA\Property(property="address_details", type="string", example="Tech Park, Building 3"),
     * @OA\Property(property="delivery_instructions", type="string", example="Leave with reception"),
     * @OA\Property(property="is_default", type="boolean", example=false)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Customer address updated successfully",
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Customer address updated successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="user_id", type="integer", example=1),
     * @OA\Property(property="label", type="string", example="Work Updated"),
     * @OA\Property(property="latitude", type="number", format="float", example=31.51234567),
     * @OA\Property(property="longitude", type="number", format="float", example=34.45678901),
     * @OA\Property(property="address_details", type="string", example="Tech Park, Building 3"),
     * @OA\Property(property="delivery_instructions", type="string", example="Leave with reception"),
     * @OA\Property(property="is_default", type="boolean", example=false)
     * )
     * )
     * ),
     * @OA\Response(response=404, description="Customer address not found"),
     * @OA\Response(response=422, description="Validation error"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(CustomerAddressesRequest $request, CustomerAddress $customerAddresses)
    {
//        if ($customerAddress->user_id !== auth()->id()) {
//            return $this->errorResponse(__('This action is unauthorized.'), 403);
//        }

        $validatedData = $request->validated();

        if ($request->input('is_default') == true){
            CustomerAddress::where('user_id', auth()->user()->id)
                ->where('id', '!=' ,$customerAddresses->id)
                ->update(['is_default' => false]);
        }

        $customerAddresses->update($validatedData);

        return $this->successResponse(
            new CustomerAdressesResource($customerAddresses),
            __('Customer address updated successfully')
        );
    }

    /**
     * Delete a customer address.
     *
     * @OA\Delete(
     * path="/api/v1/delivery/customer-addresses/{id}",
     * summary="Delete a customer address",
     * tags={"Customer Addresses"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=false,
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\Response(
     * response=200,
     * description="Customer address deleted successfully",
     * @OA\JsonContent(
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Customer address deleted successfully")
     * )
     * ),
     * @OA\Response(response=404, description="Customer address not found"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy($id)
    {
        $customerAddresses = CustomerAddress::findOrFail($id);
        $customerAddresses->delete();

        return $this->successResponse(
            null,
            __('Customer address deleted successfully')
        );
    }
}

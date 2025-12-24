<?php

namespace Modules\Items\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\Items\Entities\Attribute;
use Modules\Items\Transformers\V1\AttributeResource;
use Spatie\QueryBuilder\QueryBuilder;

class AttributeController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/items/attributes",
     * summary="Get all attributes",
     * description="Retrieve a list of all attributes with their values",
     * tags={"Items"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar")
     * ),
     * @OA\Parameter(
     * name="filter[name]",
     * in="query",
     * required=false,
     * description="Filter attributes by name",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * description="Number of items per page",
     * @OA\Schema(type="integer", example=15)
     * ),
     * @OA\Response(
     * response=200,
     * description="Attributes retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="message", type="string", example="Attributes retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="Color"),
     * @OA\Property(
     * property="attribute_values",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=10),
     * @OA\Property(property="name", type="string", example="Red")
     * )
     * )
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
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $attributes = QueryBuilder::for(Attribute::class)
            ->allowedFilters(['name']) // تحديد الفلاتر المسموح بها
            ->with(['attributeValues']) // **مهم جداً: تحميل قيم السمات معها**
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            AttributeResource::collection($attributes), // **استخدام AttributeResource الصحيح**
            __('Attributes retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/items/attributes/{attribute}",
     * summary="Get a single attribute",
     * description="Retrieve details of a specific attribute by ID, including its values",
     * tags={"Items"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar")
     * ),
     * @OA\Parameter(
     * name="attribute",
     * in="path",
     * required=true,
     * description="Attribute ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Attribute retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="message", type="string", example="Attribute retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="Color"),
     * @OA\Property(
     * property="attribute_values",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=10),
     * @OA\Property(property="name", type="string", example="Red")
     * )
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Attribute not found")
     * )
     */
    public function show(Attribute $attribute)
    {
        // **استخدام Route Model Binding الصحيح لـ Attribute**
        $attribute->load(['attributeValues']); // تحميل قيم السمات معها
        return $this->successResponse(
            new AttributeResource($attribute), // **استخدام AttributeResource الصحيح**
            __('Attribute retrieved successfully')
        );
    }

//    /**
//     * @OA\Delete(
//     * path="/api/v1/items/attributes/{attribute}",
//     * summary="Delete an attribute",
//     * description="Soft delete a specific attribute by ID",
//     * tags={"Items"},
//     * security={{"sanctum": {}}},
//     * @OA\Parameter(
//     * name="attribute",
//     * in="path",
//     * required=true,
//     * description="Attribute ID",
//     * @OA\Schema(type="integer")
//     * ),
//     * @OA\Response(
//     * response=200,
//     * description="Attribute deleted successfully"
//     * ),
//     * @OA\Response(response=401, description="Unauthenticated"),
//     * @OA\Response(response=404, description="Attribute not found")
//     * )
//     */
//    public function destroy(Attribute $attribute)
//    {
//        $attribute->delete(); // Assuming Attribute model uses SoftDeletes trait
//
//        return $this->successResponse(
//            null,
//            __('Attribute deleted successfully')
//        );
//    }
}


<?php

namespace Modules\Items\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Entities\User;
use Modules\Core\Http\Controllers\Api\V1\Auth\LoginController;
use Modules\Core\Http\Requests\UserRequest;
use Modules\Core\Traits\ApiResponse;
use Modules\Items\Entities\Product;
use Modules\Items\Http\Requests\CategoryRequest;
use Modules\Items\Entities\Category;
use Modules\Items\Transformers\V1\ProductResource;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/items/products",
     *     summary="Get all products",
     *     description="Retrieve a list of all products",
     *     tags={"Items"},
     *     @OA\Parameter(
     *          name="Accept-Language",
     *          in="header",
     *          required=true,
     *          description="Language header (ar or en)",
     *          @OA\Schema(
     *              type="string",
     *              default="ar",
     *              example="en"
     *          )
     *      ),
     *      @OA\Parameter(
     *  name="include",
     *  in="query",
     *  required=false,
     *  description="Include related resources. e.g., 'variations.attributeValues.attribute'",
     *  @OA\Schema(type="string")
     *  ),
     *     @OA\Parameter(
     *         name="fields",
     *         in="query",
     *         required=false,
     *         description="Comma separated list of fields to be returned",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[discount]",
     *         in="query",
     *         required=false,
     *         description="Filter by discount (exact)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Products retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Product Name"),
     *                     @OA\Property(property="description", type="string", example="Product Description")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page');
        $maxPerPage = config('core.pagination.max_per_page');
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $products = QueryBuilder::for(Product::class)
            ->allowedFields(ProductResource::allowedFields())
            ->allowedFilters(ProductResource::allowedFilters())
            ->allowedSorts(ProductResource::allowedSorts())
            ->allowedIncludes(ProductResource::allowedIncludes())
            ->with(['media', 'categories'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            ProductResource::collection($products),
            __('Products retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/items/products/{product}",
     *     summary="Get a single product",
     *     description="Retrieve details of a specific product by ID",
     *     tags={"Items"},
     *     @OA\Parameter(
     *           name="Accept-Language",
     *           in="header",
     *           required=true,
     *           description="Language header (ar or en)",
     *           @OA\Schema(
     *               type="string",
     *               default="ar",
     *               example="en"
     *           )
     *       ),
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         required=true,
     *         description="Product ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *          name="fields",
     *          in="path",
     *          required=false,
     *          description="Comma separated list of fields to be returned",
     *          @OA\Schema(type="string", example="id,name,description")
     *      ),
     *     @OA\Response(
     *         response=200,
     *         description="Product retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Product Name"),
     *                 @OA\Property(property="description", type="string", example="Product Description")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function show(Product $product)
    {
        $product->load(['media', 'translations' , 'variations.attributeValues.attribute', 'attributes', 'activeOffers']);
        return $this->successResponse(
            new ProductResource($product),
            __('Product retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/items/products/{product}/attributes",
     * summary="Get all attributes for a product",
     * description="Retrieve a list of all attributes associated with a specific product",
     * tags={"Items"},
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
     * name="product",
     * in="path",
     * required=true,
     * description="Product ID",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Product attributes retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Product attributes retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="Color"),
     * @OA\Property(property="value", type="string", example="Red")
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Product not found")
     * )
     */
    public function getAttributes(Product $product)
    {
        $product->load('variations.attributeValues.attribute');

        $allAttributeValues = $product->variations->flatMap(function ($variation) {
            return $variation->attributeValues;
        });

        $groupedByAttribute = $allAttributeValues->groupBy('attribute.id');

        $attributes = $groupedByAttribute->map(function ($values, $attributeId) {
            $attribute = $values->first()->attribute;

            $attribute->setRelation('attributeValues', $values->unique('id')->values());

            return $attribute;
        });

        return $this->successResponse(
            \Modules\Items\Transformers\V1\AttributeResource::collection($attributes->values()),
            __('Product attributes retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/items/products/{product}/addons",
     * summary="Get all addons for a product",
     * description="Retrieve a list of all addons associated with a specific product",
     * tags={"Items"},
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
     * name="product",
     * in="path",
     * required=true,
     * description="Product ID",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Product addons retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Product addons retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Product not found")
     * )
     */

    public function getAddons(Product $product)
    {
        $product->load('addons' , 'addons.addonOptions');
        return $this->successResponse(
            \Modules\Items\Transformers\V1\AddonResource::collection($product->addons),
            __('Product addons retrieved successfully')
        );
    }

    public function destroy($id) {}
}

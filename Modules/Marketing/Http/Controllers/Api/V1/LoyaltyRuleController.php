<?php

namespace Modules\Marketing\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\Marketing\Entities\LoyaltyRule;
use Modules\Marketing\Transformers\V1\LoyaltyRuleResource;
use Spatie\QueryBuilder\QueryBuilder;

class LoyaltyRuleController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/marketing/loyalty-rules",
     * summary="Get all loyalty rules",
     * description="Retrieve a list of all loyalty rules",
     * tags={"Marketing"},
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
     * name="include",
     * in="query",
     * required=false,
     * description="Include related resources. e.g., 'createdBy'",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="fields",
     * in="query",
     * required=false,
     * description="Comma separated list of fields to be returned",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="filter[type]",
     * in="query",
     * required=false,
     * description="Filter by rule type (exact)",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * required=false,
     * description="Number of items per page",
     * @OA\Schema(type="integer", example=10)
     * ),
     * @OA\Parameter(
     * name="page",
     * in="query",
     * required=false,
     * description="Page number",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Response(
     * response=200,
     * description="Loyalty rules retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Loyalty rules retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="New User Bonus"),
     * @OA\Property(property="type", type="string", example="fixed_bonus")
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

        $loyaltyRules = QueryBuilder::for(LoyaltyRule::class)
            ->allowedFields(LoyaltyRuleResource::allowedFields())
            ->allowedFilters(LoyaltyRuleResource::allowedFilters())
            ->allowedSorts(LoyaltyRuleResource::allowedSorts())
            ->allowedIncludes(LoyaltyRuleResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            LoyaltyRuleResource::collection($loyaltyRules),
            __('Loyalty rules retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/marketing/loyalty-rules/{loyaltyRule}",
     * summary="Get a single loyalty rule",
     * description="Retrieve details of a specific loyalty rule by ID",
     * tags={"Marketing"},
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
     * name="loyaltyRule",
     * in="path",
     * required=true,
     * description="Loyalty Rule ID",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(
     * name="fields",
     * in="path",
     * required=false,
     * description="Comma separated list of fields to be returned",
     * @OA\Schema(type="string", example="id,name,description")
     * ),
     * @OA\Response(
     * response=200,
     * description="Loyalty rule retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Loyalty rule retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="New User Bonus"),
     * @OA\Property(property="type", type="string", example="fixed_bonus")
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Loyalty rule not found")
     * )
     */
    public function show(LoyaltyRule $loyaltyRule)
    {
        $loyaltyRule->load(['translations']);
        return $this->successResponse(
            new LoyaltyRuleResource($loyaltyRule),
            __('Loyalty Rule retrieved successfully')
        );
    }

    public function destroy($id) {}
}

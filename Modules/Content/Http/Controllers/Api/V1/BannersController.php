<?php

namespace Modules\Content\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Entities\Banner;
use Modules\Content\Transformers\V1\BannerResource;
use Modules\Core\Traits\ApiResponse;
use Spatie\QueryBuilder\QueryBuilder;

class BannersController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/content/banners",
     *     summary="Get all banners",
     *     description="Retrieve a list of all banners",
     *     tags={"Content"},
     *     security={{"sanctum": {}}},
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
     *     @OA\Parameter(
     *         name="fields",
     *         in="query",
     *         required=false,
     *         description="Comma separated list of fields to be returned",
     *         @OA\Schema(type="string")
     *     ),
     *  @OA\Parameter(
     *    name="filter[name]",
     *    in="query",
     *    required=false,
     *    description="Filter by translated name (LIKE)",
     *    @OA\Schema(type="string")
     *  ),
     *  @OA\Parameter(
     *    name="filter[id]",
     *    in="query",
     *    required=false,
     *    description="Filter by id (exact)",
     *    @OA\Schema(type="integer")
     *  ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         required=false,
     *         description="Sort fields. Use leading '-' for DESC. Examples: name, -name, slug, -id",
     *         @OA\Schema(type="string", example="asc")
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
     *         description="BAnners retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Banners retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Banner Name"),
     *                     @OA\Property(property="branch_id", type="integer", example="Branch Id"),
     *                     @OA\Property(property="description", type="string", example="Banner Description")
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

        $banners = QueryBuilder::for(Banner::class)
            ->allowedFields(BannerResource::allowedFields())
            ->allowedFilters(BannerResource::allowedFilters())
            ->allowedSorts(BannerResource::allowedSorts())
            ->with(['media'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            BannerResource::collection($banners),
            __('Banners retrieved successfully')
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('content::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('content::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('content::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}

<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Entities\Branch;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Http\Requests\CompanyRequest;
use Modules\PPUDS\Transformers\V1\CompanyResource;
use Spatie\QueryBuilder\QueryBuilder;

class CompanyController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/companies",
     * summary="Get all companies",
     * description="Retrieve a list of all companies",
     * tags={"Companies"},
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
     * name="fields",
     * in="query",
     * required=false,
     * description="Comma separated list of fields to be returned",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="filter[id]",
     * in="query",
     * required=false,
     * description="Filter by ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="filter[name]",
     * in="query",
     * required=false,
     * description="Filter by translated name (LIKE)",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="filter[email]",
     * in="query",
     * required=false,
     * description="Filter by email",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="filter[is_active]",
     * in="query",
     * required=false,
     * description="Filter by is_active status",
     * @OA\Schema(type="boolean")
     * ),
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * required=false,
     * description="Sort fields. Use leading '-' for DESC. Examples: name, -id",
     * @OA\Schema(type="string", example="name")
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
     * description="Companies retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Companies retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="Company Name"),
     * @OA\Property(property="description", type="string", example="Company Description"),
     * @OA\Property(property="email", type="string", example="company@example.com"),
     * @OA\Property(property="phone", type="string", example="0599999999"),
     * @OA\Property(property="logo", type="string", example="https://domain.com/storage/logo.png"),
     * @OA\Property(property="is_active", type="boolean", example=true)
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $companies = QueryBuilder::for(Company::class)
            ->allowedFields(CompanyResource::allowedFields())
            ->allowedFilters(CompanyResource::allowedFilters())
            ->allowedSorts(CompanyResource::allowedSorts())
            ->with(['media', 'translations'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            CompanyResource::collection($companies),
            __('Companies retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/companies",
     * summary="Create a new company with branches and departments",
     * description="Creates a company, uploads logo, creates associated branches, and their departments.",
     * tags={"Companies"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * required={"name", "company_category_id", "status", "branches"},
     * @OA\Property(property="name", type="string", example="New Tech Company"),
     * @OA\Property(property="website", type="string", example="https://example.com"),
     * @OA\Property(property="description", type="string", example="Leading tech solutions"),
     * @OA\Property(property="company_category_id", type="integer", example=1),
     * @OA\Property(property="status", type="string", example="active", enum={"active", "inactive"}),
     * @OA\Property(property="logo", type="string", format="binary", description="Company Logo Image"),
     * @OA\Property(
     * property="branches",
     * type="array",
     * description="List of branches",
     * @OA\Items(
     * type="object",
     * required={"name", "country_id", "city_id", "latitude", "longitude", "opening_time", "closing_time"},
     * @OA\Property(property="name", type="string", example="Main Branch"),
     * @OA\Property(property="email", type="string", format="email", example="branch@example.com"),
     * @OA\Property(property="phone", type="string", example="+970599999999"),
     * @OA\Property(property="country_id", type="integer", example=1),
     * @OA\Property(property="city_id", type="integer", example=1),
     * @OA\Property(property="latitude", type="number", format="float", example=31.9038),
     * @OA\Property(property="longitude", type="number", format="float", example=35.2034),
     * @OA\Property(property="opening_time", type="string", example="08:00"),
     * @OA\Property(property="closing_time", type="string", example="17:00"),
     * @OA\Property(
     * property="departments",
     * type="array",
     * description="Departments inside this branch",
     * @OA\Items(
     * type="object",
     * required={"name"},
     * @OA\Property(property="name", type="string", example="HR Department")
     * )
     * )
     * )
     * )
     * )
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Company created successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Company created successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * description="The created company object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="New Tech Company"),
     * @OA\Property(property="description", type="string", example="Leading tech solutions"),
     * @OA\Property(property="email", type="string", example="company@example.com"),
     * @OA\Property(property="logo", type="string", example="https://domain.com/storage/logo.png"),
     * @OA\Property(property="is_active", type="boolean", example=true)
     * )
     * )
     * ),
     * @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function store(CompanyRequest $request)
    {
        $company = DB::transaction(function () use ($request) {

            $companyData = $request->safe()->except(['branches', 'logo']);
            $companyData['created_by'] = auth()->id();

            $company = Company::create($companyData);

            if ($request->hasFile('logo')) {
                $company->addMediaFromRequest('logo')->toMediaCollection('logo');
            }

            if ($request->has('branches')) {
                foreach ($request->branches as $branchData) {

                    $departmentsData = $branchData['departments'] ?? [];
                    $branchAttributes = collect($branchData)->except(['departments'])->toArray();

                    $branchAttributes['created_by'] = auth()->id();

                    $branch = Branch::create($branchAttributes);

                    $company->branches()->attach($branch->id, ['is_main' => false]);

                    if (!empty($departmentsData)) {
                        foreach ($departmentsData as $dept) {
                            $branch->departments()->create([
                                'name'       => $dept['name'],
                                'created_by' => auth()->id(),
                            ]);
                        }
                    }
                }
            }

            return $company;
        });

        $company->load(['media', 'branches.departments', 'translations']);

        return $this->successResponse(
            new CompanyResource($company),
            __('Company created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/companies/{company}",
     * summary="Get a single company",
     * description="Retrieve details of a specific company by ID",
     * tags={"Companies"},
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
     * name="company",
     * in="path",
     * required=true,
     * description="Company ID",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(
     * name="fields",
     * in="query",
     * required=false,
     * description="Comma separated list of fields to be returned",
     * @OA\Schema(type="string", example="id,name,description")
     * ),
     * @OA\Response(
     * response=200,
     * description="Company retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Company retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="Company Name"),
     * @OA\Property(property="description", type="string", example="Company Description"),
     * @OA\Property(property="email", type="string", example="company@example.com"),
     * @OA\Property(property="phone", type="string", example="0599999999"),
     * @OA\Property(property="logo", type="string", example="https://domain.com/storage/logo.png"),
     * @OA\Property(property="is_active", type="boolean", example=true)
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=404, description="Company not found")
     * )
     */
    public function show(Company $company)
    {
        $company = QueryBuilder::for(Company::class)
            ->where('id', $company->id)
            ->allowedFields(CompanyResource::allowedFields())
            ->with(['media', 'translations'])
            ->firstOrFail();

        return $this->successResponse(
            new CompanyResource($company),
            __('Company retrieved successfully')
        );
    }
}

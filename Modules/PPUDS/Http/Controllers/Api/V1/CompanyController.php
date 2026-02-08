<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Entities\Branch;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment; // تأكد من استدعاء هذا الكلاس
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
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include relations (e.g. branches)",
     * @OA\Schema(type="string")
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
     * @OA\Property(property="name", type="string", example="New Tech Company"),
     * @OA\Property(property="description", type="string", example="Leading tech solutions"),
     * @OA\Property(property="company_category_id", type="integer", example=5),
     * @OA\Property(property="website", type="string", example="https://example.com"),
     * @OA\Property(property="status", type="integer", example=1),
     * @OA\Property(property="logo_url", type="string", example="https://domain.com/storage/logo.png"),
     * @OA\Property(property="created_at", type="string", format="date-time"),
     * @OA\Property(
     * property="branches",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=10),
     * @OA\Property(property="name", type="string", example="Main Branch")
     * )
     * )
     * )
     * )
     * )
     * )
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
            ->allowedIncludes(CompanyResource::allowedIncludes())
            ->with(['media', 'translations']) // يمكنك إضافة branches إذا أردت عرضها في القائمة
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
     * summary="Create a new company",
     * description="Creates company, branches, and links departments with supervisors.",
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
     * @OA\Property(property="status", type="integer", example=1),
     * @OA\Property(property="logo", type="string", format="binary"),
     * @OA\Property(
     * property="branches",
     * type="array",
     * @OA\Items(
     * type="object",
     * required={"name", "country_id", "city_id", "latitude", "longitude", "opening_time", "closing_time"},
     * @OA\Property(property="name", type="string", example="Main Branch"),
     * @OA\Property(property="email", type="string", format="email", example="branch@example.com"),
     * @OA\Property(property="phone", type="string", example="+970599999999"),
     * @OA\Property(property="country_id", type="integer", example=1),
     * @OA\Property(property="city_id", type="integer", example=1),
     * @OA\Property(property="latitude", type="number", example=31.90),
     * @OA\Property(property="longitude", type="number", example=35.20),
     * @OA\Property(property="opening_time", type="string", example="08:00"),
     * @OA\Property(property="closing_time", type="string", example="17:00"),
     * @OA\Property(
     * property="departments",
     * type="array",
     * @OA\Items(
     * type="object",
     * required={"name", "user_id"},
     * @OA\Property(property="name", type="string", example="HR"),
     * @OA\Property(property="user_id", type="integer", example=5, description="Supervisor ID")
     * )
     * )
     * )
     * )
     * )
     * )
     * ),
     * @OA\Response(response=201, description="Created successfully")
     * )
     */
    // في ملف CompanyController.php

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
                    $workingHoursData = $branchData['working_hours'] ?? [];

                    $branchAttributes = collect($branchData)
                        ->except(['departments', 'working_hours'])
                        ->toArray();

                    $branchAttributes['created_by'] = auth()->id();

                    $branch = Branch::create($branchAttributes);

                    $company->branches()->attach($branch->id, ['is_main' => false]);

                    if (!empty($workingHoursData)) {
                        foreach ($workingHoursData as $wh) {
                            $branch->workingHours()->create([
                                'day'        => $wh['day'],
                                'is_closed'  => $wh['is_closed'] ?? false,
                                'start_time' => ($wh['is_closed'] ?? false) ? null : ($wh['start_time'] ?? null),
                                'end_time'   => ($wh['is_closed'] ?? false) ? null : ($wh['end_time'] ?? null),
                            ]);
                        }
                    }

                    if (!empty($departmentsData)) {
                        foreach ($departmentsData as $deptData) {
                            $deptName = $deptData['name'];
                            $supervisorId = $deptData['user_id'] ?? null;

                            $department = CompanyDepartment::whereTranslation('name', $deptName)->first();

                            if (! $department) {
                                $department = CompanyDepartment::create([
                                    'name'       => $deptName,
                                    'created_by' => auth()->id(),
                                ]);
                            }

                            $branch->departments()->syncWithoutDetaching([
                                $department->id => [
                                    'user_id' => $supervisorId
                                ]
                            ]);
                        }
                    }
                }
            }

            return $company;
        });

        $company->load(['media', 'branches.departments', 'branches.workingHours', 'translations']); // أضفنا branches.workingHours

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
     * description="Retrieve details of a specific company",
     * tags={"Companies"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="company",
     * in="path",
     * required=true,
     * description="Company ID",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\Parameter(
     * name="include",
     * in="query",
     * required=false,
     * description="Include relations (e.g. branches.departments)",
     * @OA\Schema(type="string")
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
     * @OA\Property(property="name", type="string", example="New Tech Company"),
     * @OA\Property(property="description", type="string", example="Leading tech solutions"),
     * @OA\Property(property="company_category_id", type="integer", example=5),
     * @OA\Property(property="website", type="string", example="https://example.com"),
     * @OA\Property(property="status", type="integer", example=1),
     * @OA\Property(property="logo_url", type="string", example="https://domain.com/storage/logo.png"),
     * @OA\Property(property="created_at", type="string", format="date-time"),
     * @OA\Property(
     * property="branches",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=10),
     * @OA\Property(property="name", type="string", example="Main Branch"),
     * @OA\Property(property="city_id", type="integer", example=2)
     * )
     * )
     * )
     * )
     * ),
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

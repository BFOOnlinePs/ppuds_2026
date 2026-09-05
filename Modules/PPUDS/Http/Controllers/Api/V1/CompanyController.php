<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Transformers\V1\BranchResource;
use Modules\Core\Enums\UserRole;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment; // تأكد من استدعاء هذا الكلاس
use Modules\PPUDS\Http\Requests\CompanyRequest;
use Modules\PPUDS\Http\Requests\CompanyUpdateRequest;
use Modules\PPUDS\Http\Requests\UpdateCompanyBranchLocationRequest;
use Modules\PPUDS\Services\PpuApiService;
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
     * @OA\Property(property="contact_person", type="string", example="Ahmad Ali"),
     * @OA\Property(property="contact_info", type="string", example="0599123456 - ahmad@example.com"),
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
     * @OA\Property(property="contact_person", type="string", example="Ahmad Ali"),
     * @OA\Property(property="contact_info", type="string", example="0599123456 - ahmad@example.com"),
     * @OA\Property(property="description", type="string", example="Leading tech solutions"),
     * @OA\Property(property="company_category_id", type="integer", example=1),
     * @OA\Property(property="status", type="integer", example=1),
     * @OA\Property(property="logo", type="string", format="binary"),
     * @OA\Property(
     * property="branches",
     * type="array",
     * @OA\Items(
     * type="object",
     * required={"name", "country_id", "city_id", "opening_time", "closing_time"},
     * @OA\Property(property="name", type="string", example="Main Branch"),
     * @OA\Property(property="email", type="string", format="email", example="branch@example.com"),
     * @OA\Property(property="phone", type="string", example="+970599999999"),
     * @OA\Property(property="manager_name", type="string", example="Mohammad Ahmad"),
     * @OA\Property(property="manager_phone", type="string", example="+970599888777"),
     * @OA\Property(property="country_id", type="integer", example=1),
     * @OA\Property(property="city_id", type="integer", example=1),
     * @OA\Property(property="latitude", type="number", nullable=true, example=31.90),
     * @OA\Property(property="longitude", type="number", nullable=true, example=35.20),
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

                    $branchAttributes = $this->nullifyBranchCoordinates($branchAttributes);

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

        $company->load([
            'media',
            'branches.departments.supervisors',
            'branches.workingHours',
            'branches.supervisors',
            'translations',
        ]);

        app(PpuApiService::class)->addCompanyToUniversity($company, userId: auth()->id());

        return $this->successResponse(
            new CompanyResource($company),
            __('Company created successfully'),
            201
        );
    }


    /**
     * @OA\Patch(
     * path="/api/v1/ppuds/companies/{company}",
     * summary="Update a company (Partial Update / PATCH)",
     * description="Partially updates company data, branches, or logo. Note: Due to multipart/form-data constraints, send a POST request with '_method=PATCH' in the body.",
     * tags={"Companies"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="company",
     * in="path",
     * required=true,
     * description="Company ID",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * @OA\Property(property="_method", type="string", example="PATCH", description="MANDATORY: Tells Laravel to process this POST request as a PATCH"),
     * @OA\Property(property="name", type="string", example="Updated Tech Company"),
     * @OA\Property(property="website", type="string", example="https://updated-example.com"),
     * @OA\Property(property="contact_person", type="string", example="Ahmad Ali"),
     * @OA\Property(property="contact_info", type="string", example="0599123456 - ahmad@example.com"),
     * @OA\Property(property="description", type="string", example="Updated description"),
     * @OA\Property(property="status", type="integer", example=1),
     * @OA\Property(property="logo", type="string", format="binary", description="Upload new logo to replace the old one"),
     * @OA\Property(
     * property="branches",
     * type="array",
     * description="Array of branches. Send branch ID to update, omit ID to create a new one.",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=10, description="Send ID to update existing branch"),
     * @OA\Property(property="name", type="string", example="Updated Main Branch"),
     * @OA\Property(property="manager_name", type="string", example="Mohammad Ahmad"),
     * @OA\Property(property="manager_phone", type="string", example="+970599888777"),
     * @OA\Property(property="country_id", type="integer", example=1),
     * @OA\Property(property="city_id", type="integer", example=1),
     * @OA\Property(property="latitude", type="number", nullable=true, example=31.90),
     * @OA\Property(property="longitude", type="number", nullable=true, example=35.20)
     * )
     * )
     * )
     * )
     * ),
     * @OA\Response(response=200, description="Company updated successfully"),
     * @OA\Response(response=404, description="Company not found")
     * )
     */
    public function update(CompanyUpdateRequest $request, Company $company)
    {
        $company = DB::transaction(function () use ($request, $company) {
            // استبعاد البيانات غير المباشرة
            $companyData = $request->safe()->except(['branches', 'logo', '_method']);

            // تحديث بيانات الشركة (بما أننا PATCH، سيتم تحديث الحقول المرسلة فقط)
            if (!empty($companyData)) {
                $company->update($companyData);
            }

            // تحديث اللوجو إذا تم رفعه
            if ($request->hasFile('logo')) {
                $company->clearMediaCollection('logo');
                $company->addMediaFromRequest('logo')->toMediaCollection('logo');
            }

            // تحديث الفروع (إذا تم إرسال مصفوفة الفروع فقط)
            if ($request->has('branches')) {
                foreach ($request->branches as $branchData) {
                    $departmentsData  = $branchData['departments'] ?? [];
                    $workingHoursData = $branchData['working_hours'] ?? [];

                    $branchAttributes = collect($branchData)
                        ->except(['id', 'departments', 'working_hours'])
                        ->toArray();

                    $branchAttributes = $this->normalizeBranchCoordinates($branchAttributes);

                    if (isset($branchData['id']) && $branchData['id']) {
                        // تحديث فرع موجود
                        $branch = Branch::findOrFail($branchData['id']);
                        $branch->update($branchAttributes);
                    } else {
                        // إضافة فرع جديد من شاشة التعديل
                        $branchAttributes['created_by'] = auth()->id();
                        $branch = Branch::create($branchAttributes);
                        $company->branches()->attach($branch->id, ['is_main' => false]);
                    }

                    // تحديث ساعات العمل (إذا تم إرسالها)
                    if (isset($branchData['working_hours'])) {
                        $branch->workingHours()->delete();
                        foreach ($workingHoursData as $wh) {
                            $branch->workingHours()->create([
                                'day'        => $wh['day'],
                                'is_closed'  => $wh['is_closed'] ?? false,
                                'start_time' => ($wh['is_closed'] ?? false) ? null : ($wh['start_time'] ?? null),
                                'end_time'   => ($wh['is_closed'] ?? false) ? null : ($wh['end_time'] ?? null),
                            ]);
                        }
                    }

                    // تحديث الأقسام (إذا تم إرسالها)
                    if (isset($branchData['departments'])) {
                        $syncDepartments = [];
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

                            $syncDepartments[$department->id] = ['user_id' => $supervisorId];
                        }
                        $branch->departments()->sync($syncDepartments);
                    }
                }
            }

            return $company;
        });

        $company->load([
            'media',
            'branches.departments.supervisors',
            'branches.workingHours',
            'branches.supervisors',
            'translations',
        ]);

        return $this->successResponse(
            new CompanyResource($company),
            __('Company updated successfully'),
            200
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/companies/{company}/branches/{branch}/location",
     * summary="Get company branch location",
     * description="Retrieve the current location data for a company branch.",
     * tags={"Companies"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="integer", example=1)),
     * @OA\Parameter(name="branch", in="path", required=true, @OA\Schema(type="integer", example=10)),
     * @OA\Response(response=200, description="Company branch location retrieved successfully"),
     * @OA\Response(response=422, description="Branch does not belong to company")
     * )
     */
    public function showBranchLocation(
        Company $company,
        Branch $branch
    ) {
        if (! $this->branchBelongsToCompany($company, $branch)) {
            return $this->errorResponse(__('The selected branch does not belong to this company.'), 422);
        }

        $branch->load(['translations', 'workingHours', 'departments.translations']);

        return $this->successResponse(
            new BranchResource($branch),
            __('Company branch location retrieved successfully')
        );
    }

    /**
     * @OA\Patch(
     * path="/api/v1/ppuds/companies/{company}/branches/{branch}/location",
     * summary="Update company branch location",
     * description="Allows a company supervisor assigned to the branch to set the branch coordinates from the current device location.",
     * tags={"Companies"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="integer", example=1)),
     * @OA\Parameter(name="branch", in="path", required=true, @OA\Schema(type="integer", example=10)),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"latitude", "longitude"},
     * @OA\Property(property="latitude", type="number", format="float", example=31.5326),
     * @OA\Property(property="longitude", type="number", format="float", example=35.0998)
     * )
     * ),
     * @OA\Response(response=200, description="Company location updated successfully"),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=422, description="Branch does not belong to company or validation error")
     * )
     */
    public function updateBranchLocation(
        UpdateCompanyBranchLocationRequest $request,
        Company $company,
        Branch $branch
    ) {
        if (! $this->branchBelongsToCompany($company, $branch)) {
            return $this->errorResponse(__('The selected branch does not belong to this company.'), 422);
        }

        if (! $this->canUpdateBranchLocation($branch)) {
            return $this->errorResponse(__('You are not allowed to update this branch location.'), 403);
        }

        $branch->forceFill([
            'latitude' => $this->formatCoordinate($request->latitude),
            'longitude' => $this->formatCoordinate($request->longitude),
        ])->save();

        $branch->load(['translations', 'workingHours', 'departments.translations']);

        return $this->successResponse(
            new BranchResource($branch),
            __('Company location updated successfully')
        );
    }

    private function normalizeBranchCoordinates(array $branchAttributes): array
    {
        foreach (['latitude', 'longitude'] as $coordinate) {
            if (array_key_exists($coordinate, $branchAttributes) && blank($branchAttributes[$coordinate])) {
                $branchAttributes[$coordinate] = null;
            }
        }

        return $branchAttributes;
    }

    private function nullifyBranchCoordinates(array $branchAttributes): array
    {
        foreach (['latitude', 'longitude'] as $coordinate) {
            $branchAttributes[$coordinate] = null;
        }

        return $branchAttributes;
    }

    private function branchBelongsToCompany(Company $company, Branch $branch): bool
    {
        return $company->branches()
            ->whereKey($branch->getKey())
            ->exists();
    }

    private function canUpdateBranchLocation(Branch $branch): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ])) {
            return true;
        }

        if (! $user->hasRole(UserRole::COMPANY_SUPERVISOR->value)) {
            return false;
        }

        return DB::table(config('ppuds.table_prefix').'branch_department')
            ->where('branch_id', $branch->getKey())
            ->where('user_id', $user->id)
            ->exists();
    }

    private function formatCoordinate(mixed $coordinate): string
    {
        return number_format((float) $coordinate, 8, '.', '');
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
     * @OA\Property(property="contact_person", type="string", example="Ahmad Ali"),
     * @OA\Property(property="contact_info", type="string", example="0599123456 - ahmad@example.com"),
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
            ->allowedIncludes(CompanyResource::allowedIncludes())
            ->with([
                'media',
                'branches.departments.supervisors',
                'branches.workingHours',
                'branches.supervisors',
                'translations',
            ])
            ->firstOrFail();

        return $this->successResponse(
            new CompanyResource($company),
            __('Company retrieved successfully')
        );
    }
}

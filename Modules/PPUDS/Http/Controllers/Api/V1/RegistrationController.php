<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Http\Requests\RegistrationRequest;
use Modules\PPUDS\Http\Requests\RegistrationUpdateRequest;
use Modules\PPUDS\Transformers\V1\RegistrationResource;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 * name="Registrations",
 * description="API Endpoints for managing student registrations"
 * )
 */
class RegistrationController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/registrations",
     * summary="List all registrations",
     * description="Retrieve a list of registrations with filtering and sorting",
     * tags={"Registrations"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="filter[student_id]",
     * in="query",
     * description="Filter by Student ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="filter[course_id]",
     * in="query",
     * description="Filter by Course ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Parameter(
     * name="filter[semester]",
     * in="query",
     * description="Filter by Semester (e.g., First, Second, Summer)",
     * @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     * name="filter[year]",
     * in="query",
     * description="Filter by Year",
     * @OA\Schema(type="string")
     * ),
     * @OA\Response(
     * response=200,
     * description="Registrations retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/RegistrationResource"))
     * )
     * )
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $registrations = QueryBuilder::for(Registration::class)
            ->allowedFields(RegistrationResource::allowedFields())
            ->allowedFilters(RegistrationResource::allowedFilters())
            ->allowedSorts(RegistrationResource::allowedSorts())
            ->allowedIncludes(RegistrationResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            RegistrationResource::collection($registrations),
            __('Registrations retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/registrations",
     * summary="Create a new registration",
     * description="Submit a new registration record with optional image upload",
     * tags={"Registrations"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * required={"student_id", "course_id", "semester", "year"},
     * @OA\Property(property="student_id", type="integer", example=10),
     * @OA\Property(property="course_id", type="integer", example=2),
     * @OA\Property(property="grade", type="string", example="A"),
     * @OA\Property(property="semester", type="string", example="First"),
     * @OA\Property(property="year", type="string", example="2024"),
     * @OA\Property(property="supervisor_id", type="integer", example=5),
     * @OA\Property(property="university_score", type="number", format="float", example=85.5),
     * @OA\Property(property="company_score", type="number", format="float", example=90.0),
     * @OA\Property(property="image", type="string", format="binary", description="Upload registration file/image")
     * )
     * )
     * ),
     * @OA\Response(response=201, description="Created successfully")
     * )
     */
    public function store(RegistrationRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        $registration = Registration::create($data);

        if ($request->hasFile('final_file')) {
            $registration->addImage($request->file('final_file'));
        }

        return $this->successResponse(
            new RegistrationResource($registration),
            __('Registration created successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/registrations/{registration}",
     * summary="Get registration details",
     * tags={"Registrations"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="registration", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Success")
     * )
     */
    public function show(Registration $registration)
    {
        $registration = QueryBuilder::for(Registration::class)
            ->where('id', $registration->id)
            ->allowedFields(RegistrationResource::allowedFields())
            ->allowedIncludes(RegistrationResource::allowedIncludes())
            ->firstOrFail();

        return $this->successResponse(
            new RegistrationResource($registration),
            __('Registration retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/registrations/{registration}",
     * summary="Update registration",
     * description="Update registration details. Use _method=PUT for multipart form-data to update image.",
     * tags={"Registrations"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(name="registration", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * @OA\Property(property="_method", type="string", example="PUT"),
     * @OA\Property(property="grade", type="string", example="A+"),
     * @OA\Property(property="university_score", type="number", format="float", example=95.0),
     * @OA\Property(property="company_score", type="number", format="float", example=98.0),
     * @OA\Property(property="image", type="string", format="binary", description="Upload new registration file/image")
     * )
     * )
     * ),
     * @OA\Response(response=200, description="Updated successfully")
     * )
     */
    public function update(RegistrationUpdateRequest $request, Registration $registration)
    {
        $registration->update($request->validated());

        if ($request->hasFile('final_file')) {
            $registration->addImage($request->file('final_file'));
        }

        return $this->successResponse(
            new RegistrationResource($registration->refresh()),
            __('Registration updated successfully')
        );
    }
}

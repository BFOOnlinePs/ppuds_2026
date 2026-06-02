<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Entities\User;
use Modules\Core\Http\Controllers\Api\V1\Auth\LoginController;
use Modules\Core\Http\Requests\UserRequestUpdate;
use Modules\Core\Traits\ApiResponse;
use Modules\Core\Transformers\V1\UserResource;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     summary="Get all users",
     *     description="Retrieve a list of all users",
     *     tags={"Users"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="fields",
     *         in="query",
     *         required=false,
     *         description="Comma separated list of fields to be returned",
     *
     *         @OA\Schema(type="string", example="id,name,email")
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Anas"),
     *                     @OA\Property(property="email", type="string", example="anas@example.com")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page');
        $maxPerPage = config('core.pagination.max_per_page');
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $users = QueryBuilder::for(User::class)
            ->with('media')
            ->allowedFields(UserResource::allowedFields())
            ->allowedSorts(UserResource::allowedSorts())
            ->allowedFilters(UserResource::allowedFilters())
            ->allowedIncludes(UserResource::allowedIncludes())
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            UserResource::collection($users),
            __('Users retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{user}",
     *     summary="Get a single user",
     *     description="Retrieve details of a specific user by ID",
     *     tags={"Users"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *          name="fields",
     *          in="path",
     *          required=false,
     *          description="Comma separated list of fields to be returned",
     *
     *          @OA\Schema(type="string", example="id,name,email")
     *      ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Anas"),
     *                 @OA\Property(property="email", type="string", example="anas@example.com")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function show(User $user)
    {
        $user = QueryBuilder::for(User::class)
            ->allowedFields(UserResource::allowedFields())
            ->allowedSorts(UserResource::allowedSorts())
            ->allowedIncludes(UserResource::allowedIncludes())
            ->findOrFail($user->id);

        return $this->successResponse(
            new UserResource($user),
            __('User retrieved successfully')
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LoginController $request) {}

    /**
     * @OA\Patch(
     *     path="/api/v1/users/{user}",
     *     summary="Update user",
     *     description="Update the details of a specific user by ID, including student profile data",
     *     tags={"Users"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *          name="include",
     *          in="query",
     *          required=false,
     *          description="Comma separated relations to return, for example: studentProfile,studentProfile.major,roles,media",
     *
     *          @OA\Schema(type="string", example="studentProfile")
     *      ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="Anas"),
     *             @OA\Property(property="email", type="string", format="email", example="anas@example.com"),
     *             @OA\Property(property="phone", type="string", example="+970599123456"),
     *             @OA\Property(
     *                 property="studentProfile",
     *                 type="object",
     *                 @OA\Property(property="dob", type="string", format="date", nullable=true, example="2001-01-01"),
     *                 @OA\Property(property="gender", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="tawjihi_gpa", type="number", nullable=true, example=95.5),
     *                 @OA\Property(property="enrollment_year", type="integer", nullable=true, example=2022),
     *                 @OA\Property(property="semester_level", type="integer", nullable=true, example=6),
     *                 @OA\Property(property="major_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="linkedin_url", type="string", nullable=true, example="https://www.linkedin.com/in/student"),
     *                 @OA\Property(property="behance_url", type="string", nullable=true, example="https://www.behance.net/student"),
     *                 @OA\Property(property="github_url", type="string", nullable=true, example="https://github.com/student")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Anas"),
     *                 @OA\Property(property="email", type="string", example="anas@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+970599123456")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function update(UserRequestUpdate $request, User $user)
    {
        $data = $request->validated();
        $studentProfileData = $data['studentProfile'] ?? [];
        $userData = collect($data)
            ->except(['studentProfile', 'cv', 'avatar', 'cover_photo'])
            ->toArray();

        if ($userData !== []) {
            $user->update($userData);
        }

        $studentProfile = $user->studentProfile;

        if ($studentProfileData !== []) {
            if (! $studentProfile) {
                return $this->errorResponse(__('Student profile not found'), 404);
            }

            $studentProfile->update($studentProfileData);
        }

        if ($request->hasFile('cv')) {
            if (! $studentProfile) {
                return $this->errorResponse(__('Student profile not found'), 404);
            }

            $studentProfile->addMediaFromRequest('cv')
                ->toMediaCollection('cv', 'student_profiles');
        }

        if ($request->hasFile('cover_photo')) {
            $user->addMediaFromRequest('cover_photo')
                ->toMediaCollection('cover_photo', 'media');
        }

        if ($request->hasFile('avatar')) {

            $user->addMediaFromRequest('avatar')
                ->toMediaCollection('avatar', 'media');
        }

        return $this->successResponse(
            new UserResource($this->responseUser($user, $request)),
            __('User updated successfully')
        );
    }

    private function responseUser(User $user, Request $request): User
    {
        $includes = collect(explode(',', (string) $request->query('include', '')))
            ->map(fn (string $include): string => trim($include))
            ->filter()
            ->intersect(UserResource::allowedIncludes())
            ->values()
            ->all();

        return $user->fresh()->load($includes);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/v1/users/{user}",
     *     summary="Delete user",
     *     description="Delete a specific user by ID",
     *     tags={"Users"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User deleted successfully"),
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return $this->successResponse(
            null,
            __('User deleted successfully')
        );
    }
}

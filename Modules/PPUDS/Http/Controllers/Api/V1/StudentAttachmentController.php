<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Http\Requests\StudentAttachmentRequest;
use Modules\PPUDS\Http\Requests\StudentAttachmentUpdate;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;
use Modules\PPUDS\Transformers\V1\StudentAttachmentResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 * name="Student Attachments",
 * description="API Endpoints for managing the optional attachments of a student profile"
 * )
 */
class StudentAttachmentController extends Controller
{
    use ApiResponse;
    use ScopesStudentCompanyVisibility;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/student-attachments",
     * summary="List student attachments",
     * description="Retrieve the optional attachments of the students visible to the authenticated user",
     * tags={"Student Attachments"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=false,
     * description="Language header (ar or en)",
     *
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     *
     * @OA\Parameter(
     * name="filter[student_id]",
     * in="query",
     * description="Filter by student user ID",
     *
     * @OA\Schema(type="integer", example=42)
     * ),
     *
     * @OA\Parameter(
     * name="filter[student_profile_id]",
     * in="query",
     * description="Filter by student profile ID",
     *
     * @OA\Schema(type="integer", example=7)
     * ),
     *
     * @OA\Parameter(
     * name="filter[student_number]",
     * in="query",
     * description="Filter by the university student number",
     *
     * @OA\Schema(type="string", example="202012345")
     * ),
     *
     * @OA\Parameter(
     * name="filter[name]",
     * in="query",
     * description="Filter by attachment name (partial match)",
     *
     * @OA\Schema(type="string")
     * ),
     *
     * @OA\Parameter(
     * name="sort",
     * in="query",
     * description="Sort by id, name, size or created_at (prefix with - for descending)",
     *
     * @OA\Schema(type="string", example="-created_at")
     * ),
     *
     * @OA\Parameter(
     * name="per_page",
     * in="query",
     * description="Items per page",
     *
     * @OA\Schema(type="integer", example=10)
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Student attachments retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Student attachments retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     *
     * @OA\Items(ref="#/components/schemas/StudentAttachmentResource")
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

        $attachments = QueryBuilder::for($this->visibleAttachmentsQuery())
            ->allowedFields(StudentAttachmentResource::allowedFields())
            ->allowedFilters(StudentAttachmentResource::allowedFilters())
            ->allowedSorts(StudentAttachmentResource::allowedSorts())
            ->allowedIncludes(StudentAttachmentResource::allowedIncludes())
            ->with('model')
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            StudentAttachmentResource::collection($attachments),
            __('Student attachments retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/student-attachments",
     * summary="Upload student attachments",
     * description="Upload one or more optional attachments to a student profile. When student_id is omitted the attachment is stored on the authenticated student's own profile. The name is applied only when a single file is uploaded; otherwise every file keeps its own name.",
     * tags={"Student Attachments"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     *
     * @OA\Schema(
     *
     * @OA\Property(property="student_id", type="integer", nullable=true, description="Student user ID. Defaults to the authenticated user", example=42),
     * @OA\Property(property="name", type="string", nullable=true, description="Optional attachment name, defaults to the uploaded file name", example="Training agreement"),
     * @OA\Property(property="attachment", type="string", format="binary", description="Optional single PDF, Word, Excel or image file"),
     * @OA\Property(
     * property="attachments[]",
     * type="array",
     * description="Optional multiple files (max 10)",
     *
     * @OA\Items(type="string", format="binary")
     * )
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=201,
     * description="Student attachment uploaded successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Student attachment uploaded successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     *
     * @OA\Items(ref="#/components/schemas/StudentAttachmentResource")
     * )
     * )
     * ),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=404, description="Student profile not found"),
     * @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StudentAttachmentRequest $request)
    {
        $studentId = $request->studentId();

        abort_unless($this->canAccessStudentUser($studentId), 403);

        $profile = StudentProfile::query()
            ->where('user_id', $studentId)
            ->first();

        if (! $profile) {
            return $this->errorResponse(__('Student profile not found'), 404);
        }

        $files = $request->attachmentFiles();

        if ($files === []) {
            return $this->errorResponse(__('No attachment file was provided'), 422);
        }

        $name = $request->input('name');

        $attachments = collect($files)
            ->map(fn ($file): ?Media => $profile->addAttachment(
                $file,
                count($files) === 1 ? $name : null
            ))
            ->filter()
            ->values();

        if ($attachments->isEmpty()) {
            return $this->errorResponse(__('Attachment upload failed'), 422);
        }

        return $this->successResponse(
            StudentAttachmentResource::collection($attachments),
            __('Student attachment uploaded successfully'),
            201
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/student-attachments/{studentAttachment}",
     * summary="Get student attachment details",
     * tags={"Student Attachments"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="studentAttachment",
     * in="path",
     * required=true,
     * description="Attachment (media) ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Student attachment retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Student attachment retrieved successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/StudentAttachmentResource")
     * )
     * ),
     * @OA\Response(response=404, description="Attachment not found")
     * )
     */
    public function show(int $studentAttachment)
    {
        $attachment = QueryBuilder::for($this->visibleAttachmentsQuery())
            ->where('id', $studentAttachment)
            ->allowedFields(StudentAttachmentResource::allowedFields())
            ->allowedIncludes(StudentAttachmentResource::allowedIncludes())
            ->with('model')
            ->firstOrFail();

        return $this->successResponse(
            new StudentAttachmentResource($attachment),
            __('Student attachment retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/student-attachments/{studentAttachment}",
     * summary="Update student attachment",
     * description="Rename an attachment and/or replace its file. Use _method=PATCH for multipart support.",
     * tags={"Student Attachments"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="studentAttachment",
     * in="path",
     * required=true,
     * description="Attachment (media) ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\RequestBody(
     *
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     *
     * @OA\Schema(
     *
     * @OA\Property(property="_method", type="string", example="PATCH"),
     * @OA\Property(property="name", type="string", example="Updated attachment name"),
     * @OA\Property(property="attachment", type="string", format="binary", description="Optional replacement file")
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Student attachment updated successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Student attachment updated successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/StudentAttachmentResource")
     * )
     * ),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=404, description="Attachment not found")
     * )
     */
    public function update(StudentAttachmentUpdate $request, int $studentAttachment)
    {
        $attachment = $this->visibleAttachmentsQuery()
            ->where('id', $studentAttachment)
            ->with('model')
            ->firstOrFail();

        $profile = $attachment->model;

        abort_unless($profile instanceof StudentProfile, 404);
        abort_unless($this->canAccessStudentUser((int) $profile->user_id), 403);

        if ($request->filled('name')) {
            $attachment->name = $request->input('name');
            $attachment->save();
        }

        if ($file = $request->file('attachment')) {
            $replacement = $profile->addAttachment($file, $attachment->name);

            if (! $replacement) {
                return $this->errorResponse(__('Attachment upload failed'), 422);
            }

            $attachment->delete();
            $attachment = $replacement;
        }

        return $this->successResponse(
            new StudentAttachmentResource($attachment),
            __('Student attachment updated successfully')
        );
    }

    /**
     * @OA\Delete(
     * path="/api/v1/ppuds/student-attachments/{studentAttachment}",
     * summary="Delete student attachment",
     * tags={"Student Attachments"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="studentAttachment",
     * in="path",
     * required=true,
     * description="Attachment (media) ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Student attachment deleted successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Student attachment deleted successfully"),
     * @OA\Property(property="data", type="null", nullable=true, example=null)
     * )
     * ),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=404, description="Attachment not found")
     * )
     */
    public function destroy(int $studentAttachment)
    {
        $attachment = $this->visibleAttachmentsQuery()
            ->where('id', $studentAttachment)
            ->with('model')
            ->firstOrFail();

        $profile = $attachment->model;

        abort_unless($profile instanceof StudentProfile, 404);
        abort_unless($this->canAccessStudentUser((int) $profile->user_id), 403);

        $attachment->delete();

        return $this->successResponse(
            null,
            __('Student attachment deleted successfully')
        );
    }

    private function visibleAttachmentsQuery(): Builder
    {
        return Media::query()
            ->where('model_type', (new StudentProfile)->getMorphClass())
            ->where('collection_name', StudentProfile::ATTACHMENTS_COLLECTION)
            ->whereIn('model_id', $this->visibleStudentProfileIdsQuery());
    }

    private function visibleStudentProfileIdsQuery(): Builder
    {
        return $this
            ->applyStudentProfileVisibilityScope(StudentProfile::query())
            ->select('id');
    }
}

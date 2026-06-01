<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Note;
use Modules\PPUDS\Http\Requests\NoteRequest; // تأكد من إنشاء هذا الملف
use Modules\PPUDS\Http\Requests\NoteRequestUpdate;
use Modules\PPUDS\Transformers\V1\NoteResource;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(name="Notes", description="API Endpoints for Student Notes")
 */
class NoteController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/notes",
     * summary="Get all notes",
     * description="Retrieve a list of all notes with filtering and sorting. Usually filtered by the authenticated user.",
     * tags={"Notes"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="filter[name]",
     * in="query",
     * description="Filter by note title",
     *
     * @OA\Schema(type="string")
     * ),
     *
     * @OA\Parameter(
     * name="filter[is_pinned]",
     * in="query",
     * description="Filter by pinned status",
     *
     * @OA\Schema(type="boolean")
     * ),
     *
     * @OA\Parameter(
     * name="filter[month]",
     * in="query",
     * description="Filter notes by month. Use YYYY-MM, or use a month number with filter[year].",
     *
     * @OA\Schema(type="string", example="2026-06")
     * ),
     *
     * @OA\Parameter(
     * name="filter[year]",
     * in="query",
     * description="Filter notes by year. Useful with filter[month]=6.",
     *
     * @OA\Schema(type="integer", example=2026)
     * ),
     *
     * @OA\Parameter(
     * name="filter[current_month]",
     * in="query",
     * description="When true, return notes from the current month.",
     *
     * @OA\Schema(type="boolean", example=true)
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Notes retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Notes retrieved successfully"),
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/NoteResource"))
     * )
     * )
     * )
     */
    public function index()
    {
        $defaultPerPage = config('core.pagination.per_page', 10);
        $maxPerPage = config('core.pagination.max_per_page', 100);
        $perPage = min(request('per_page', $defaultPerPage), $maxPerPage);

        $notes = QueryBuilder::for(Note::class)
            ->where('user_id', auth()->id())
            ->allowedFields(NoteResource::allowedFields())
            ->allowedFilters(NoteResource::allowedFilters())
            ->allowedSorts(NoteResource::allowedSorts())
            ->allowedIncludes(NoteResource::allowedIncludes())
            ->with(['media', 'translations'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            NoteResource::collection($notes),
            __('Notes retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/notes",
     * summary="Create a new note",
     * tags={"Notes"},
     * security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     *
     * @OA\Schema(
     * required={"name", "content", "note_date"},
     *
     * @OA\Property(property="name", type="string", example="اجتماع التدريب الميداني"),
     * @OA\Property(property="content", type="string", example="تم مناقشة خطة العمل للأسبوع القادم"),
     * @OA\Property(property="note_date", type="string", format="date", example="2026-03-01"),
     * @OA\Property(property="is_pinned", type="boolean", example=false),
     * @OA\Property(property="note_image", type="string", format="binary", description="Optional image for the note")
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=201,
     * description="Note created successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Note created successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/NoteResource")
     * )
     * )
     * )
     */
    public function store(NoteRequest $request)
    {
        $note = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $data['user_id'] = auth()->id();
            $data['created_by'] = auth()->id();

            $note = Note::create($data);

            if ($request->hasFile('note_image')) {
                $note->addImage($request->file('note_image'));
            }

            return $note;
        });

        return $this->successResponse(
            new NoteResource($note),
            __('Note created successfully'),
            201
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/notes/{id}",
     * summary="Update an existing note",
     * description="Update note details. Use _method=PUT in form-data if uploading a new image.",
     * tags={"Notes"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="Note ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\RequestBody(
     * required=true,
     *
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     *
     * @OA\Schema(
     *
     * @OA\Property(property="_method", type="string", example="PUT"),
     * @OA\Property(property="name", type="string", example="تحديث عنوان الملاحظة"),
     * @OA\Property(property="content", type="string", example="تحديث التفاصيل..."),
     * @OA\Property(property="note_date", type="string", format="date", example="2026-03-05"),
     * @OA\Property(property="is_pinned", type="boolean", example=true),
     * @OA\Property(property="note_image", type="string", format="binary")
     * )
     * )
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Note updated successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Note updated successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/NoteResource")
     * )
     * )
     * )
     */
    public function update(NoteRequestUpdate $request, Note $note)
    {
        // التحقق من الملكية
        if ($note->user_id !== auth()->id()) {
            return $this->errorResponse(__('Unauthorized access'), 403);
        }

        $updatedNote = DB::transaction(function () use ($request, $note) {
            $data = $request->validated();
            $currentLocale = app()->getLocale();

            $note->update($data);

            if ($request->hasFile('note_image')) {
                $note->addImage($request->file('note_image'));
            }

            return $note;
        });

        return $this->successResponse(
            new NoteResource($updatedNote),
            __('Note updated successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/notes/{id}",
     * summary="Get note details",
     * tags={"Notes"},
     * security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="Note ID",
     *
     * @OA\Schema(type="integer")
     * ),
     *
     * @OA\Response(
     * response=200,
     * description="Note retrieved successfully",
     *
     * @OA\JsonContent(
     * type="object",
     *
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="data", ref="#/components/schemas/NoteResource")
     * )
     * )
     * )
     */
    public function show(Note $note)
    {
        if ($note->user_id !== auth()->id()) {
            return $this->errorResponse(__('Unauthorized access to this note'), 403);
        }

        $noteRecord = QueryBuilder::for(Note::class)
            ->where('id', $note->id)
            ->allowedFields(NoteResource::allowedFields())
            ->with(['media', 'translations'])
            ->firstOrFail();

        return $this->successResponse(
            new NoteResource($noteRecord),
            __('Note retrieved successfully')
        );
    }
}

<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Entities\Payment;
use Modules\PPUDS\Http\Requests\PaymentRequest;
use Modules\PPUDS\Http\Requests\PaymentRequestUpdate;
use Modules\PPUDS\Transformers\V1\PaymentResource;
use Spatie\QueryBuilder\QueryBuilder;

class PaymentController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/payments",
     * summary="Get all payments",
     * description="Retrieve a list of all payments with filtering and sorting",
     * tags={"Payments"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\Parameter(
     * name="filter[student_company_id]",
     * in="query",
     * required=false,
     * description="Filter by Student Company ID",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Payments retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Payments retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="array",
     * @OA\Items(type="object")
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

        $payments = QueryBuilder::for(Payment::class)
            ->allowedFields(PaymentResource::allowedFields())
            ->allowedFilters(PaymentResource::allowedFilters())
            ->allowedSorts(PaymentResource::allowedSorts())
            ->allowedIncludes(PaymentResource::allowedIncludes())
            ->with(['media'])
            ->paginate($perPage)
            ->appends(request()->query());

        return $this->successResponse(
            PaymentResource::collection($payments),
            __('Payments retrieved successfully')
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/payments",
     * summary="Create a new payment",
     * description="Create a new payment record and upload receipt",
     * tags={"Payments"},
     * security={{"sanctum": {}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * required={"student_company_id", "payment_value", "currency_id", "status"},
     * @OA\Property(property="student_company_id", type="integer", example=1),
     * @OA\Property(property="payment_value", type="number", format="float", example=150.50),
     * @OA\Property(property="currency_id", type="integer", example=1),
     * @OA\Property(property="status", type="integer", example=1, description="See PaymentStatus Enum"),
     * @OA\Property(property="reference_id", type="string", example="REF-12345"),
     * @OA\Property(property="company_notes", type="string", example="First installment"),
     * @OA\Property(property="receipt", type="string", format="binary", description="Payment receipt image")
     * )
     * )
     * ),
     * @OA\Response(response=201, description="Payment created successfully")
     * )
     */
    public function store(PaymentRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        $payment = Payment::create($data);

        if ($request->hasFile('receipt')) {
            // نستخدم الدالة الموجودة في موديل Payment
            $payment->addImage($request->file('receipt'));
        }

        $payment->load(['studentCompany', 'currency']);

        return $this->successResponse(
            new PaymentResource($payment),
            __('Payment created successfully'),
            201
        );
    }

    /**
     * @OA\Post(
     * path="/api/v1/ppuds/payments/{payment}",
     * summary="Update an existing payment",
     * description="Update payment details including receipt image.
     * **Note:** You must use `POST` method with `_method` parameter set to `PUT` or `PATCH` to support file uploads in PHP.",
     * tags={"Payments"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="payment",
     * in="path",
     * description="Payment ID",
     * required=true,
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * description="Language (ar or en)",
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\MediaType(
     * mediaType="multipart/form-data",
     * @OA\Schema(
     * required={"_method"},
     * @OA\Property(
     * property="_method",
     * type="string",
     * example="PUT",
     * description="REQUIRED: Must be 'PUT' or 'PATCH' to spoof the HTTP method."
     * ),
     * @OA\Property(
     * property="student_company_id",
     * type="integer",
     * description="Student Company ID (Optional)",
     * example=5
     * ),
     * @OA\Property(
     * property="payment_value",
     * type="number",
     * format="float",
     * description="Amount (Optional)",
     * example=150.50
     * ),
     * @OA\Property(
     * property="currency_id",
     * type="integer",
     * description="Currency ID (Optional)",
     * example=1
     * ),
     * @OA\Property(
     * property="status",
     * type="integer",
     * description="Payment Status (1:Pending, 2:Approved, 3:Rejected)",
     * example=2
     * ),
     * @OA\Property(
     * property="reference_id",
     * type="string",
     * description="External Reference ID (Optional)",
     * example="REF-2024-001"
     * ),
     * @OA\Property(
     * property="company_notes",
     * type="string",
     * description="Notes or remarks (Optional)",
     * example="Approved after reviewing receipt."
     * ),
     * @OA\Property(
     * property="supervisor_id",
     * type="integer",
     * description="Assign a supervisor ID (Optional)",
     * example=10
     * ),
     * @OA\Property(
     * property="student_role",
     * type="string",
     * description="Role of the student submitting (Optional)",
     * example="Treasurer"
     * ),
     * @OA\Property(
     * property="receipt",
     * type="string",
     * format="binary",
     * description="New receipt image file (Optional). Replaces the old one."
     * )
     * )
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Payment updated successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Payment updated successfully"),
     * )
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation Error",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="The given data was invalid."),
     * @OA\Property(property="errors", type="object")
     * )
     * ),
     * @OA\Response(
     * response=404,
     * description="Payment not found"
     * )
     * )
     */
    public function update(PaymentRequestUpdate $request, Payment $payment)
    {
        $payment->update($request->validated());

        return $this->successResponse(
            new PaymentResource($payment->refresh()),
            __('Payment updated successfully')
        );
    }

    /**
     * @OA\Get(
     * path="/api/v1/ppuds/payments/{payment}",
     * summary="Get a single payment",
     * description="Retrieve details of a specific payment",
     * tags={"Payments"},
     * security={{"sanctum": {}}},
     * @OA\Parameter(
     * name="payment",
     * in="path",
     * required=true,
     * description="Payment ID",
     * @OA\Schema(type="integer", example=1)
     * ),
     * @OA\Parameter(
     * name="Accept-Language",
     * in="header",
     * required=true,
     * description="Language header (ar or en)",
     * @OA\Schema(type="string", default="ar", example="en")
     * ),
     * @OA\Response(
     * response=200,
     * description="Payment retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="data", type="object")
     * )
     * ),
     * @OA\Response(response=404, description="Payment not found")
     * )
     */
    public function show(Payment $payment)
    {
        $payment = QueryBuilder::for(Payment::class)
            ->where('id', $payment->id)
            ->allowedFields(PaymentResource::allowedFields())
            ->allowedIncludes(PaymentResource::allowedIncludes())
            ->firstOrFail();

        return $this->successResponse(
            new PaymentResource($payment),
            __('Payment retrieved successfully')
        );
    }
}

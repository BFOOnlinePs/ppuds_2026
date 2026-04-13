<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'student_company_id' => $this->student_company_id,
            'payment_value'      => (float) $this->payment_value,
            'currency_id'        => $this->currency_id,
            'status'             => $this->status,
            'status_label'       => $this->status?->getLabel(), // إذا كان الـ Enum يدعم getLabel
            'reference_id'       => $this->reference_id,
            'student_notes'      => $this->student_notes,
            'company_notes'      => $this->company_notes,
            'receipt_url'        => $this->image, // Accessor from Payment model: getImageAttribute
            'created_by'         => $this->created_by,
            'created_at'         => $this->created_at,

            // العلاقات
            'student_company'    => new StudentCompanyResource($this->whenLoaded('studentCompany')),
            'currency'           => $this->whenLoaded('currency'), // يمكن استخدام CurrencyResource إذا وجد
            'supervisor'         => $this->whenLoaded('supervisor'),
            'creator'            => $this->whenLoaded('createdBy'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'student_company_id',
            'payment_value',
            'currency_id',
            'status',
            'reference_id',
            'created_by',
            'created_at'
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('student_company_id'),
            AllowedFilter::exact('currency_id'),
            AllowedFilter::exact('status'),
            AllowedFilter::exact('supervisor_id'),
            AllowedFilter::exact('created_by'),

            AllowedFilter::callback('student_id', function (Builder $query, $value) {
                $query->whereHas('studentCompany.registration', function ($query) use ($value) {
                    $query->where('student_id', $value);
                });
            }),
            AllowedFilter::callback('company_id', function (Builder $query, $value) {
                $query->whereHas('studentCompany.registration', function ($query) use ($value) {
                    $query->where('company_id', $value);
                });
            }),
            AllowedFilter::callback('semester', function (Builder $query, $value) {
                $query->whereHas('studentCompany.registration', function ($query) use ($value) {
                    $query->where('semester', $value);
                });
            }),
            AllowedFilter::callback('year', function (Builder $query, $value) {
                $query->whereHas('studentCompany.registration', function ($query) use ($value) {
                    $query->where('year', $value);
                });
            }),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('payment_value'),
            AllowedSort::field('created_at'),
            AllowedSort::field('status'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'studentCompany',
            'studentCompany.student',
            'currency',
            'supervisor',
            'createdBy'
        ];
    }
}

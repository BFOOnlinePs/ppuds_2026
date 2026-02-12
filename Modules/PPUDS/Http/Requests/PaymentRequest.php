<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PPUDS\Enums\PaymentStatus;

class PaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'student_company_id' => ['required', 'integer', 'exists:ppu_ds_student_companies,id'], // تأكد من اسم الجدول إذا كان مختلف
            'currency_id'        => ['required', 'integer', 'exists:core_currencies,id'], // افترضنا اسم جدول العملات
            'payment_value'      => ['required', 'numeric', 'min:0'],
            'status'             => ['required', 'integer', 'in:' . implode(',', array_column(PaymentStatus::cases(), 'value'))],
            'reference_id'       => ['nullable', 'string', 'max:255'],
            'company_notes'      => ['nullable', 'string'],
            'supervisor_id'      => ['nullable', 'integer', 'exists:users,id'],
            'student_role'       => ['nullable', 'string', 'max:255'],
            'receipt'            => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}

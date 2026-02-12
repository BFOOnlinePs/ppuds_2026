<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // مهم جداً
use Modules\PPUDS\Entities\StudentCompany; // استدعاء موديل الشركة الطلابية
use Modules\Core\Entities\Currency; // استدعاء موديل العملة
use Modules\PPUDS\Enums\PaymentStatus;

class PaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // الحل هنا: نستخدم Rule::exists مع اسم الكلاس، ولارافيل سيحضر اسم الجدول الصحيح تلقائياً
            'student_company_id' => ['required', 'integer', Rule::exists(StudentCompany::class, 'id')],

            // نفس الشيء للعملة
            'currency_id'        => ['required', 'integer', Rule::exists(Currency::class, 'id')],

            'payment_value'      => ['required', 'numeric', 'min:0'],

            // التحقق من الحالة
            'status'             => ['required', 'integer', Rule::in(array_column(PaymentStatus::cases(), 'value'))],

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

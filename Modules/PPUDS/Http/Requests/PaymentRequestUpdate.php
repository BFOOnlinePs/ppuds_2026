<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // مهم جداً
use Modules\PPUDS\Enums\PaymentStatus;

class PaymentRequestUpdate extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status'                => ['required', 'integer', Rule::in(array_column(PaymentStatus::cases(), 'value'))],
            'company_notes'         => ['nullable', 'string']
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

<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Entities\Company;

class FieldVisitCompanyStudentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists((new Company)->getTable(), 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

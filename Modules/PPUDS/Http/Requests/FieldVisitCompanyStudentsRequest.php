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
            'filter' => ['required', 'array'],
            'filter.company_id' => ['required', 'integer', Rule::exists((new Company)->getTable(), 'id')],
            'filter.supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'filter.search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

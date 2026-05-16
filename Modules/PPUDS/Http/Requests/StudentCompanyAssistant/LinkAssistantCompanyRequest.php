<?php

namespace Modules\PPUDS\Http\Requests\StudentCompanyAssistant;

use Illuminate\Foundation\Http\FormRequest;

class LinkAssistantCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'registration_id' => ['required', 'integer', 'exists:' . config('ppuds.table_prefix') . 'registrations,id'],
            'company_id' => ['required', 'integer', 'exists:' . config('ppuds.table_prefix') . 'companies,id'],
            'branch_id' => ['nullable', 'integer', 'exists:' . config('branch.table_prefix') . 'branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:' . config('ppuds.table_prefix') . 'company_departments,id'],
            'reason' => ['nullable', 'string', 'max:280'],
            'fit_score' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('StudentCompany Create') ?? false;
    }
}

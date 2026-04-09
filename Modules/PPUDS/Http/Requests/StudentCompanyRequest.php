<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PPUDS\Enums\TrainingStatus;

class StudentCompanyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'registration_id' => ['required', 'integer', 'exists:' . config('ppuds.table_prefix') . 'registrations,id'],
            'company_id'      => ['required', 'integer', 'exists:' . config('ppuds.table_prefix') . 'companies,id'],
            'branch_id'       => ['nullable', 'integer', 'exists:' . config('branch.table_prefix') . 'branches,id'],
            'department_id'   => ['nullable', 'integer', 'exists:' . config('ppuds.table_prefix') . 'company_departments,id'],
            'status'          => ['required', 'integer', 'in:' . implode(',', array_column(TrainingStatus::cases(), 'value'))],
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

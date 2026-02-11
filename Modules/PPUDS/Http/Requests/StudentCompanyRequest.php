<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PPUDS\Enums\Enums\CompanyStatus;
use TrainingStatus;

class StudentCompanyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'registration_id' => ['required', 'integer', 'exists:ppu_ds_registrations,id'],
            'company_id'      => ['required', 'integer', 'exists:ppu_ds_companies,id'],
            'branch_id'       => ['nullable', 'integer', 'exists:geolocation_branches,id'],
            'department_id'   => ['nullable', 'integer', 'exists:ppu_ds_company_departments,id'],
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

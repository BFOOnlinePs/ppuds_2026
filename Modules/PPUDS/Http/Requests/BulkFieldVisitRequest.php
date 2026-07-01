<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\StudentCompany;

class BulkFieldVisitRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists((new Company)->getTable(), 'id')],
            'student_company_ids' => ['required', 'array', 'min:1', 'max:100'],
            'student_company_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists((new StudentCompany)->getTable(), 'id'),
            ],
            'supervisor_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'visiting_place' => ['required', 'string', 'max:255'],
            'visit_date' => ['required', 'date'],
            'visit_time' => ['required', 'date_format:H:i:s'],
            'visit_duration' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function studentCompanyIds(): array
    {
        return collect($this->validated('student_company_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}

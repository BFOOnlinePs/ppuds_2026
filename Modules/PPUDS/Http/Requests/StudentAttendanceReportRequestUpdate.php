<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentAttendanceReportRequestUpdate extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_feedback' => ['sometimes', 'nullable', 'string'],
            'academic_feedback' => ['sometimes', 'nullable', 'string'],
        ];
    }
}

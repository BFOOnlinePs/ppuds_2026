<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Entities\StudentCompany;

class StudentAttendanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_attendance_id' => ['required', 'integer', Rule::exists('ppu_ds_student_attendances', 'id')],
            'report_text'           => ['nullable', 'string'],
            'company_feedback'      => ['nullable', 'string'],
            'academic_feedback'     => ['nullable', 'string'],
            'submit_latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'submit_longitude'      => ['nullable', 'numeric', 'between:-180,180'],
            'file_report'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:10240'], // Max 10MB
        ];
    }
}

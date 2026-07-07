<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FieldVisitUpdate extends FormRequest
{
    public function rules(): array
    {
        return [
            'student_company_id' => ['sometimes', 'exists:' . config('ppuds.table_prefix') . 'students_companies,id'],
            'supervisor_id'      => ['sometimes', 'exists:users,id'],
            'visiting_place'     => ['sometimes', 'string', 'max:255'],
            'visit_date'         => ['sometimes', 'date'],
            'visit_time'         => ['sometimes', 'date_format:H:i:s'],
            'visit_duration'     => ['sometimes', 'integer', 'min:1'],
            'notes'              => ['sometimes', 'nullable', 'string'],
            'attachments'        => ['sometimes', 'nullable', 'array'],
            'attachments.*'      => ['file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:2048'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

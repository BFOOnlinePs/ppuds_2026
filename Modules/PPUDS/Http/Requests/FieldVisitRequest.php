<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FieldVisitRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'student_company_id' => ['required', 'exists:' . config('ppuds.table_prefix') . 'students_companies,id'],
            'supervisor_id'      => ['required', 'exists:users,id'],
            'visiting_place'     => ['required', 'string', 'max:255'],
            'visit_date'         => ['required', 'date'],
            'visit_time'         => ['required', 'date_format:H:i:s'],
            'visit_duration'     => ['required', 'integer', 'min:1'],
            'notes'              => ['nullable', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

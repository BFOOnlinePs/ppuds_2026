<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PPUDS\Enums\SemesterType;

class RegistrationUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'student_id'       => ['sometimes', 'required', 'exists:users,id'],
            'course_id'        => ['sometimes', 'required', 'exists:' . config('ppuds.table_prefix') . 'courses,id'],
            'semester'         => ['sometimes', 'required', 'in:' . implode(',', array_column(SemesterType::cases(), 'value'))],
            'year'             => ['sometimes', 'required', 'integer', 'min:2000', 'max:' . (date('Y') + 1)],

            'supervisor_id'    => ['nullable', 'exists:users,id'],
            'grade'            => ['nullable', 'string', 'max:5'],
            'university_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'company_score'    => ['nullable', 'numeric', 'min:0', 'max:100'],

            'final_file'            => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:2048'],
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

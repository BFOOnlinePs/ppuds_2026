<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SurveyAnswerRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'survey_id'                 => ['required', 'integer', 'exists:ppu_ds_surveys,id'],
            'student_company_id'        => ['nullable', 'integer', 'exists:ppu_ds_students_companies,id'],
            'answers'                   => ['required', 'array'],
            'answers.*.question_id'     => ['required', 'integer', 'exists:ppu_ds_survey_questions,id'],
            'answers.*.value'           => ['nullable'],
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

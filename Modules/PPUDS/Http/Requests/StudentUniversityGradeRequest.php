<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PPUDS\Settings\GeneralSettings;

class StudentUniversityGradeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // الحد الأقصى يأتي من الإعدادات ولا يُكتب في الكود.
            'supervisor_score' => [
                'required',
                'integer',
                'min:0',
                'max:'.app(GeneralSettings::class)->university_supervisor_max_grade,
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'supervisor_score' => __('University Supervisor Grade'),
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

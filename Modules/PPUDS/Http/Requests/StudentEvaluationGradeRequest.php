<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PPUDS\Settings\GeneralSettings;

class StudentEvaluationGradeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // الحد الأقصى يأتي من الإعدادات ولا يُكتب في الكود.
            'evaluation_score' => [
                'required',
                'integer',
                'min:0',
                'max:'.app(GeneralSettings::class)->evaluation_supervisor_max_grade,
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'evaluation_score' => __('Evaluation Supervisor Grade'),
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

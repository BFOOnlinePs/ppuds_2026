<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Enums\GigEvaluationStatus;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Enums\ReportStatus;
use Modules\PPUDS\Enums\SemesterType;

class GeneralSettingsRequestUpdate extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'semester_type'         => ['sometimes', Rule::enum(SemesterType::class)],
            'year'                  => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'report_status'         => ['sometimes', Rule::enum(ReportStatus::class)],
            'login_method'          => ['sometimes', Rule::enum(LoginMethod::class)],
            'giz_evaluation_status' => ['sometimes', Rule::enum(GigEvaluationStatus::class)],
            'start_semester'        => ['sometimes', 'date'],
            'end_semester'          => ['sometimes', 'date', 'after_or_equal:start_semester'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('General Settings Update');
    }
}

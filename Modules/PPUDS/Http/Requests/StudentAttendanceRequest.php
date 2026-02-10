<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\CompanyStatus;
use Modules\PPUDS\Enums\TrainingStatus;

class StudentAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_company_id' => [
                'required',
                'integer',
                Rule::exists(StudentCompany::class, 'id'),
            ],

            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}

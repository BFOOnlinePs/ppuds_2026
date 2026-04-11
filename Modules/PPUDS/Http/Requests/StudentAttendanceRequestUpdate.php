<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\AttendanceStatus;
use Modules\PPUDS\Enums\Enums\CompanyStatus;

class StudentAttendanceRequestUpdate extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance_date' => [
                'sometimes',
                'date',
            ],

            'status'          => ['sometimes', 'integer', 'in:' . implode(',', array_column(AttendanceStatus::cases(), 'value'))],

            'check_in' => [
                'sometimes',
                'date_format:Y-m-d H:i:s',
            ],

            'check_out' => [
                'sometimes',
                'date_format:Y-m-d H:i:s',
                'after:check_in',
            ],

            'student_company_id' => [
                'sometimes',
                'integer',
                Rule::exists(StudentCompany::class, 'id'),
            ],

            'latitude' => [
                'sometimes',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'sometimes',
                'numeric',
                'between:-180,180',
            ],

            'description' => [
                'sometimes',
                'string',
                'max:500',
            ],
        ];
    }
}

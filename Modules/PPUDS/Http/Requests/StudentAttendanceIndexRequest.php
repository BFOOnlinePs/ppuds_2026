<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Enums\AttendanceStatus;

class StudentAttendanceIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.id' => ['nullable', 'integer'],
            'filter.student_company_id' => ['nullable', 'integer'],
            'filter.student_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'filter.company_id' => ['nullable', 'integer'],
            'filter.status' => ['nullable', 'integer', Rule::in(array_keys(AttendanceStatus::options()))],
            'filter.attendance_date' => ['nullable', 'date_format:Y-m-d'],
            'filter.attendance_date_from' => ['nullable', 'date_format:Y-m-d'],
            'filter.attendance_date_to' => ['nullable', 'date_format:Y-m-d'],
            'filter.semester' => ['nullable', 'string', 'max:20'],
            'filter.year' => ['nullable', 'string', 'max:20'],
            'sort' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('filter.attendance_date_from');
            $to = $this->input('filter.attendance_date_to');

            if ($from && $to && $to < $from) {
                $validator->errors()->add(
                    'filter.attendance_date_to',
                    __('The attendance date to must be after or equal to attendance date from.')
                );
            }
        });
    }

    public function authorize(): bool
    {
        return true;
    }
}

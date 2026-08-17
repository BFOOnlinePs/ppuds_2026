<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Branch\Entities\Branch;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\StudentGender;
use Modules\PPUDS\Enums\TrainingStatus;

class StudentStatisticsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.student_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'filter.semester' => ['nullable', 'integer', Rule::in(array_keys(SemesterType::options()))],
            'filter.year' => ['nullable', 'integer'],
            'filter.date_from' => ['nullable', 'date_format:Y-m-d'],
            'filter.date_to' => ['nullable', 'date_format:Y-m-d'],
            'filter.gender' => ['nullable', 'integer', Rule::in(array_keys(StudentGender::options()))],
            'filter.major_id' => ['nullable', 'integer', Rule::exists((new Major)->getTable(), 'id')],
            'filter.training_status' => ['nullable', 'integer', Rule::in(array_keys(TrainingStatus::options()))],
            'filter.company_id' => ['nullable', 'integer', Rule::exists((new Company)->getTable(), 'id')],
            'filter.branch_id' => ['nullable', 'integer', Rule::exists((new Branch)->getTable(), 'id')],
            'filter.department_id' => ['nullable', 'integer', Rule::exists((new CompanyDepartment)->getTable(), 'id')],
            'filter.search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('filter.date_from');
            $to = $this->input('filter.date_to');

            if ($from && $to && $to < $from) {
                $validator->errors()->add(
                    'filter.date_to',
                    __('The date to must be after or equal to date from.')
                );
            }
        });
    }

    public function authorize(): bool
    {
        return true;
    }
}

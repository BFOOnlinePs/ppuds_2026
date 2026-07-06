<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Entities\Company;

class FieldVisitCompanyStudentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'filter' => ['required', 'array'],
            'filter.company_id' => ['required', 'integer', Rule::exists((new Company)->getTable(), 'id')],
            'filter.supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'filter.supercisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'filter.search' => ['nullable', 'string', 'max:255'],
            'filter.visit_date' => ['nullable', 'date_format:Y-m-d'],
            'filter.without_visit_date' => ['nullable', 'date_format:Y-m-d'],
            'filter.visit_date_from' => ['nullable', 'date_format:Y-m-d'],
            'filter.visit_date_to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('filter.visit_date_from');
            $to = $this->input('filter.visit_date_to');

            if ($from && $to && $to < $from) {
                $validator->errors()->add(
                    'filter.visit_date_to',
                    __('The visit date to must be after or equal to visit date from.')
                );
            }
        });
    }

    public function authorize(): bool
    {
        return true;
    }
}

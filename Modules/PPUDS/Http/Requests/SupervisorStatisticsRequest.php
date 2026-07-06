<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Enums\SemesterType;

class SupervisorStatisticsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'filter.semester' => ['nullable', 'integer', Rule::in(array_keys(SemesterType::options()))],
            'filter.year' => ['nullable', 'integer'],
            'filter.date_from' => ['nullable', 'date_format:Y-m-d'],
            'filter.date_to' => ['nullable', 'date_format:Y-m-d'],
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

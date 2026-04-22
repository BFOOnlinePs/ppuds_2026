<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkExperienceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:255', 'required_without:company_id'],

            'position'     => ['required', 'string', 'max:255'],
            'sector'       => ['required', 'numeric'],
            'location'     => ['nullable', 'string', 'max:255'],

            'start_date'   => ['required', 'date', 'before_or_equal:today'],

            'end_date'     => ['nullable', 'date', 'after_or_equal:start_date'],

            'is_current'   => ['nullable', 'boolean'],
            'description'  => ['nullable', 'string', 'max:2000'],
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

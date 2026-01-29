<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PPUDS\Enums\CompanyStatus;

class CompanyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:255'],
            'website'             => ['nullable', 'url', 'max:255'],
            'description'         => ['nullable', 'string'],
            'company_category_id' => ['required', 'integer', 'exists:ppu_ds_company_categories,id'],
            'status' => ['required', 'integer', 'in:' . implode(',', array_column(CompanyStatus::cases(), 'value'))],
            'logo'                => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],

            'branches'                => ['required', 'array', 'min:1'],
            'branches.*.name'         => ['required', 'string', 'max:255'],
            'branches.*.email'        => ['nullable', 'email', 'max:255'],
            'branches.*.phone'        => ['nullable', 'string', 'max:50'],
            'branches.*.country_id'   => ['required', 'integer', 'exists:geolocation_countries,id'],
            'branches.*.city_id'      => ['required', 'integer', 'exists:geolocation_cities,id'],
            'branches.*.latitude'     => ['required', 'numeric'],
            'branches.*.longitude'    => ['required', 'numeric'],
            'branches.*.opening_time' => ['required', 'date_format:H:i'],
            'branches.*.closing_time' => ['required', 'date_format:H:i'],

            'branches.*.departments'        => ['nullable', 'array'],
            'branches.*.departments.*.name' => ['required', 'string', 'max:255'],
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

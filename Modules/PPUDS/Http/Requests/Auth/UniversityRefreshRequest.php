<?php

namespace Modules\PPUDS\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UniversityRefreshRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'refresh_token.required' => __('The refresh token is required.'),
        ];
    }
}

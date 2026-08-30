<?php

namespace Modules\PPUDS\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UniversityLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],

            // The realm's two-factor parameters, forwarded only when the app
            // sends them.
            'auth_type' => ['nullable', 'string', 'max:50'],
            'otp' => ['nullable', 'string', 'max:20'],

            'device_name' => ['nullable', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => __('The username is required.'),
            'password.required' => __('The password is required.'),
        ];
    }
}

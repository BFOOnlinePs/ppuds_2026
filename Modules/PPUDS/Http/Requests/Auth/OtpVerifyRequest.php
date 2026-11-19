<?php

namespace Modules\PPUDS\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Exchanges a sign-in code for an API token: with the email (code-only or
 * company QR sign-in), or with the login ticket of a two-step sign-in.
 */
class OtpVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required_without:login_ticket', 'nullable', 'string', 'email', 'max:255'],
            'login_ticket' => ['nullable', 'string', 'max:2000'],

            'code' => ['required', 'string', 'regex:/^\s*\d{4,8}\s*$/'],

            'qr_token' => ['nullable', 'string', 'max:255'],

            'device_name' => ['nullable', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => __('The email is required.'),
            'email.email' => __('Please enter a valid email address.'),
            'code.required' => __('The code is required.'),
            'code.regex' => __('The code is invalid or has expired.'),
        ];
    }
}

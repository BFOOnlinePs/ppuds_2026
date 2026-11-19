<?php

namespace Modules\PPUDS\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Asks for a sign-in code: for an email (code-only or company QR sign-in),
 * or — with a login ticket — again for the second step of a two-step sign-in.
 */
class OtpCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required_without:login_ticket', 'nullable', 'string', 'email', 'max:255'],

            // Two-step mode: the ticket /api/v1/auth/login returned, to resend the code.
            'login_ticket' => ['nullable', 'string', 'max:2000'],

            'channel' => ['nullable', 'string', 'in:mail,sms'],

            // Present when the sign-in started from a scanned company QR code.
            'qr_token' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => __('The email is required.'),
            'email.email' => __('Please enter a valid email address.'),
        ];
    }
}

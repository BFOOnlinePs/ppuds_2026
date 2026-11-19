<?php

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email'     => 'required|email|max:255',
            'password'  => 'required|string|max:255',
            'fcm_token'   => 'sometimes|string',
            // Two-step mode only: the channel the code goes out on, when the settings let the user choose.
            'channel'     => 'sometimes|nullable|in:mail,sms',
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

<?php

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Enums\UserRole;

class RegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            'password'  => 'required|string|max:255',
            'phone'     => ['required', 'string', 'max:255', Rule::unique('users', 'phone')],

            'fcm_token'   => 'sometimes|string',
            'device_name' => 'sometimes|string|max:255',

            'role'          => ['string', Rule::in([
                UserRole::STUDENT->value,
                UserRole::COMPANY_SUPERVISOR->value,
            ])],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => __('This phone number is already taken'),
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

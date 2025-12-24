<?php

namespace Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

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
            'phone'     => 'required|string|max:255',

            'fcm_token'   => 'sometimes|string',
            'device_name' => 'sometimes|string|max:255',
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

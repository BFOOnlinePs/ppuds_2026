<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|numeric|digits_between:10,15|unique:users,phone|regex:/^\+?[0-9]{10,15}$/',
        ];

        if ($this->method('PUT') || $this->method('PATCH'))
        {
            $userId = $this->route('user')->id;

            $rules['email'] = [
                'required',
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ];

            $rules['password'] = 'sometimes|nullable|string|min:8|max:255';

            $rules['phone'] = [
                'sometimes',
                'required',
                'numeric',
                Rule::unique('users', 'phone')->ignore($userId),
                'regex:/^\+?[0-9]{10,15}$/'
            ];

            $rules['avatar'] = 'nullable|image|mimes:jpeg,png,jpg|max:2048';
        }

        return $rules;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}

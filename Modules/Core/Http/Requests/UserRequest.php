<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Enums\StudentGender;

class UserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'name'              => 'required|string|max:255',
            'email'             => 'required|email|max:255',
            'phone'             => 'required|numeric|digits_between:10,15|unique:users,phone|regex:/^\+?[0-9]{10,15}$/',

            'studentProfile'    => 'sometimes|array',
            'studentProfile.dob' => 'sometimes|date',
            'studentProfile.gender' => 'sometimes|integer|in:' . implode(',', array_column(StudentGender::cases(), 'value')),
            'studentProfile.tawjihi_gpa' => 'sometimes|numeric|between:0,4',
            'studentProfile.enrollment_year' => 'sometimes|integer|min:1900|max:'.date('Y'),
            'studentProfile.semester_level' => 'sometimes|integer|min:1|max:12',
            'studentProfile.major_id' => 'sometimes|integer|exists:ppu_ds_majors,id',
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

            $rules['avatar']        = 'nullable|image|mimes:jpeg,png,jpg|max:2048';

            $rules['cover_photo']   = 'nullable|image|mimes:jpeg,png,jpg|max:4096';

            $rules['cv']            = 'nullable|file|mimes:pdf,doc,docx|max:5120';
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

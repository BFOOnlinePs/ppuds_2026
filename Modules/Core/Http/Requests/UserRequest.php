<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PPUDS\Enums\StudentGender;

class UserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [

            'name' => 'required|required|string|max:255',
            'email' => 'required|required|email|max:255',
            'phone' => 'required|required|numeric|regex:/^\+?[0-9]{10,15}$/',
            'password' => 'required|required|string|min:8|max:255',
            'cv' => 'sometimes|file|mimes:jpeg,png,jpg,pdf,doc,docx|max:5120',
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'cover_photo' => 'sometimes|image|mimes:jpeg,png,jpg|max:4096',

            'studentProfile' => 'sometimes|array',
            'studentProfile.dob' => 'sometimes|date',
            'studentProfile.gender' => 'sometimes|integer|in:'.implode(',', array_column(StudentGender::cases(), 'value')),
            'studentProfile.tawjihi_gpa' => 'sometimes|numeric|between:0,4',
            'studentProfile.enrollment_year' => 'sometimes|integer|min:1900|max:'.date('Y'),
            'studentProfile.semester_level' => 'sometimes|nullable|integer|min:1|max:12',
            'studentProfile.major_id' => 'sometimes|integer|exists:ppu_ds_majors,id',
            'studentProfile.linkedin_url' => 'sometimes|nullable|url|max:255',
            'studentProfile.behance_url' => 'sometimes|nullable|url|max:255',
            'studentProfile.github_url' => 'sometimes|nullable|url|max:255',
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

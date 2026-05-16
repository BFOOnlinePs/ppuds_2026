<?php

namespace Modules\PPUDS\Http\Requests\StudentCompanyAssistant;

use Illuminate\Foundation\Http\FormRequest;

class SuggestAssistantCompaniesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'registration_id' => ['nullable', 'integer', 'exists:' . config('ppuds.table_prefix') . 'registrations,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('StudentCompany Create') ?? false;
    }
}

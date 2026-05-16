<?php

namespace Modules\PPUDS\Http\Requests\StudentCompanyAssistant;

use Illuminate\Foundation\Http\FormRequest;

class SearchAssistantStudentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('StudentCompany Create') ?? false;
    }
}

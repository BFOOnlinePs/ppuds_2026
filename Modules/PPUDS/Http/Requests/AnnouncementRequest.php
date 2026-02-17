<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Core\Enums\UserRole;

class AnnouncementRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'target_roles'   => ['required', 'array'],
            'target_roles.*' => ['required', 'integer', 'in:' . implode(',', array_column(UserRole::cases(), 'value'))],
            'filters'        => ['nullable', 'array'],
            'published_at'   => ['required', 'date'],
            'expires_at'     => ['nullable', 'date', 'after:published_at'],
            'is_pinned'      => ['boolean'],
            'image'          => ['nullable', 'image', 'max:2048'],
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

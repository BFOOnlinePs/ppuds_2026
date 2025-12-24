<?php

namespace Modules\Reels\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Reels\Enums\ReelStatus;

class ReelsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'status' => 'required|integer',
            'rejection_reason' => 'nullable|string',
            'views_count' => 'nullable|integer',
            'is_visible' => 'nullable|boolean',
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-m4v,video/webm|max:50480',
            'sort_order' => 'nullable|integer',
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

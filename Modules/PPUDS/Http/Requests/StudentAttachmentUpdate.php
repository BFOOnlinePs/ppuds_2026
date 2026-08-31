<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentAttachmentUpdate extends FormRequest
{
    public function rules(): array
    {
        $fileRules = [
            'file',
            'mimes:'.implode(',', StudentAttachmentRequest::ALLOWED_ATTACHMENT_MIMES),
            'max:'.StudentAttachmentRequest::MAX_ATTACHMENT_SIZE,
        ];

        return [
            'name'       => ['sometimes', 'required', 'string', 'max:255'],
            'attachment' => ['sometimes', 'nullable', ...$fileRules],
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

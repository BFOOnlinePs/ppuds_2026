<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NoteRequestUpdate extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // استخدام sometimes يعني: تحقق من القواعد فقط إذا كان الحقل موجوداً في الطلب
            'name'       => ['sometimes', 'required', 'string', 'max:255'],
            'content'    => ['sometimes', 'required', 'string'],
            'note_date'  => ['sometimes', 'required', 'date'],
            'is_pinned'  => ['nullable', 'boolean'],
            'category'   => ['sometimes', 'string', 'in:academic,training,personal'],
            'note_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => __('Please enter a title for the note.'),
            'content.required' => __('The note content cannot be empty.'),
            'note_image.image' => __('The note image must be a file of type: jpeg, png, jpg, gif.'),
        ];
    }
}
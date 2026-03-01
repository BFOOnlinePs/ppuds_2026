<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'content'    => ['required', 'string'],
            'note_date'  => ['required', 'date'],
            'is_pinned'  => ['nullable', 'boolean'],
            'category'   => ['nullable', 'string', 'in:academic,training,personal'],
            'note_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];
    }

/*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Return custom messages for validation errors.
     *
     * This method should be overridden in Resource classes to specify custom validation messages.
     *
     * @return array
     */
/*******  d1d47fb0-cc16-4a0a-86a8-ee4cd8649dd0  *******/
    public function messages(): array
    {
        return [
            'name.required'      => __('Please enter a title for the note.'),
            'content.required'   => __('The note content cannot be empty.'),
            'note_date.required' => __('Please select a date.'),
            'note_image.image'   => __('The note image must be a file of type: jpeg, png, jpg, gif.'),
            'note_image.max'     => __('The note image may not be greater than 2MB.'),
        ];
    }
}
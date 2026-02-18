<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SurveyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'serve_group' => ['require  d', 'string', 'max:50'], // يمكن جعلها in:students,staff لو كانت قيم ثابتة
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active'   => ['boolean'],

            'questions'   => ['required', 'array', 'min:1'],

            'questions.*.content'     => ['required', 'string'],
            'questions.*.type'        => ['required', 'string', 'in:text,textarea,radio,checkbox,select,date'],
            'questions.*.is_required' => ['boolean'],
            'questions.*.sort_order'  => ['nullable', 'integer'],

            'questions.*.options' => [
                'array',
                Rule::requiredIf(function () {
                    return true; 
                }),
                'required_if:questions.*.type,radio,checkbox,select' 
            ],

            'questions.*.options.*.content'    => ['required_with:questions.*.options', 'string'],
            'questions.*.options.*.sort_order' => ['nullable', 'integer'],
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

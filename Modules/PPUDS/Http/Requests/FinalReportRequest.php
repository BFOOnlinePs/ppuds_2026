<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalReportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'role_description'             => ['nullable', 'string', 'max:20000'],
            'summary'                      => ['nullable', 'string', 'max:20000'],

            'tasks'                        => ['nullable', 'array', 'max:100'],
            'tasks.*.task_name'            => ['required', 'string', 'max:255'],
            'tasks.*.task_details'         => ['nullable', 'string', 'max:2000'],
            'tasks.*.work_duration'        => ['nullable', 'string', 'max:255'],
            'tasks.*.notes'                => ['nullable', 'string', 'max:2000'],

            'skills'                       => ['nullable', 'array', 'max:100'],
            'skills.*.skill'               => ['required', 'string', 'max:255'],
            'skills.*.mastery_percentage'  => ['nullable', 'integer', 'min:0', 'max:100'],
            'skills.*.notes'               => ['nullable', 'string', 'max:2000'],

            'contributions'                => ['nullable', 'array', 'max:100'],
            'contributions.*.content'      => ['required', 'string', 'max:2000'],

            'difficulties'                 => ['nullable', 'array', 'max:100'],
            'difficulties.*.content'       => ['required', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'role_description'            => __('Training Role Description'),
            'summary'                     => __('Summary'),
            'tasks'                       => __('Training Tasks'),
            'tasks.*.task_name'           => __('Training Task'),
            'tasks.*.task_details'        => __('Task Details'),
            'tasks.*.work_duration'       => __('Work Duration In Days And Hours'),
            'tasks.*.notes'               => __('Notes'),
            'skills'                      => __('Skills Gained From Training'),
            'skills.*.skill'              => __('Skill'),
            'skills.*.mastery_percentage' => __('Mastery Percentage'),
            'skills.*.notes'              => __('Notes'),
            'contributions'               => __('Key Contributions'),
            'contributions.*.content'     => __('Contribution'),
            'difficulties'                => __('Training Difficulties'),
            'difficulties.*.content'      => __('Difficulty'),
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

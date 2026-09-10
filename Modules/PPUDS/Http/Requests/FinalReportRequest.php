<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Services\FinalReportService;

class FinalReportRequest extends FormRequest
{
    /**
     * صيغ العرض التقديمي. يُتحقَّق منها بقاعدة mimes لأن finfo يتعرّف على
     * ملفات OOXML بنوعها الحقيقي، فلا يمكن تمرير ملف آخر بتغيير الامتداد.
     */
    public const ALLOWED_PRESENTATION_MIMES = [
        'ppt',
        'pptx',
    ];

    /**
     * ملف بايثون يُتحقَّق منه بقاعدة extensions لا mimes، لأن finfo يعيد له
     * text/x-script.python أو text/plain وكلاهما لا يُترجم إلى الامتداد py،
     * فقاعدة mimes:py كانت سترفض كل ملف بايثون سليم.
     */
    public const ALLOWED_CODE_EXTENSIONS = [
        'py',
    ];

    /**
     * ومع ذلك نقيّد المحتوى بأنواع نصية حتى لا يُرفع ملف تنفيذي بامتداد py.
     */
    public const ALLOWED_CODE_MIMETYPES = [
        'text/x-script.python',
        'text/x-python',
        'text/x-python3',
        'text/plain',
    ];

    public const MAX_PRESENTATION_SIZE = 10240;

    public const MAX_CODE_SIZE = 2048;

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'role_description'             => ['required', 'string', 'max:20000'],
            'summary'                      => ['required', 'string', 'max:20000'],

            // مرفق اختياري يُخزَّن في مجموعة final_file الموجودة على التسجيل.
            'final_file'                   => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:2048'],

            // العرض التقديمي إجباري، ويُطلب مرة واحدة فقط: إن كان مرفوعاً من
            // قبل يبقى الحقل اختيارياً حتى لا يُعاد رفعه مع كل تعديل.
            'final_presentation'           => [
                'nullable',
                Rule::requiredIf(fn (): bool => ! $this->presentationAlreadyUploaded()),
                'file',
                'mimes:' . implode(',', self::ALLOWED_PRESENTATION_MIMES),
                'max:' . self::MAX_PRESENTATION_SIZE,
            ],

            // ملف بايثون اختياري دائماً.
            'final_code'                   => [
                'nullable',
                'file',
                'extensions:' . implode(',', self::ALLOWED_CODE_EXTENSIONS),
                'mimetypes:' . implode(',', self::ALLOWED_CODE_MIMETYPES),
                'max:' . self::MAX_CODE_SIZE,
            ],

            'tasks'                        => ['required', 'array', 'max:100'],
            'tasks.*.task_name'            => ['required', 'string', 'max:255'],
            'tasks.*.task_details'         => ['required', 'string', 'max:2000'],
            'tasks.*.work_duration'        => ['required', 'string', 'max:255'],
            'tasks.*.notes'                => ['required', 'string', 'max:2000'],

            'skills'                       => ['required', 'array', 'max:100'],
            'skills.*.skill'               => ['required', 'string', 'max:255'],
            'skills.*.mastery_percentage'  => ['required', 'integer', 'min:0', 'max:100'],
            'skills.*.notes'               => ['required', 'string', 'max:2000'],

            'contributions'                => ['required', 'array', 'max:100'],
            'contributions.*.content'      => ['required', 'string', 'max:2000'],

            'difficulties'                 => ['required', 'array', 'max:100'],
            'difficulties.*.content'       => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * هل رفع الطالب عرضاً تقديمياً في تسجيله الحالي من قبل؟
     */
    protected function presentationAlreadyUploaded(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $finalReports = app(FinalReportService::class);

        return $finalReports->hasPresentation(
            $finalReports->currentRegistrationFor($user->id)
        );
    }

    public function messages(): array
    {
        return [
            'final_code.extensions' => __('The Python file must have a .py extension.'),
            'final_code.mimetypes'  => __('The Python file must be a plain text source file.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'role_description'            => __('Training Role Description'),
            'summary'                     => __('Summary'),
            'final_file'                  => __('Attachment'),
            'final_presentation'          => __('Presentation File'),
            'final_code'                  => __('Python File'),
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

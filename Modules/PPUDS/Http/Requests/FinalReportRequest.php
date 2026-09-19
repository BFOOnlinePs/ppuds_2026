<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Services\FinalReportService;

class FinalReportRequest extends FormRequest
{
    /**
     * صيغ المرفق الاختياري. أرشيف zip مسموح لملفات المشروع، والتحقق بقاعدة
     * mimes يعتمد على نوع الملف الحقيقي لا على امتداده.
     */
    public const ALLOWED_ATTACHMENT_MIMES = [
        'jpeg',
        'png',
        'jpg',
        'pdf',
        'zip',
    ];

    public const MAX_ATTACHMENT_SIZE = 10240;

    /**
     * صيغ العرض التقديمي. يُتحقَّق منها بقاعدة mimes لأن finfo يتعرّف على
     * ملفات OOXML بنوعها الحقيقي، فلا يمكن تمرير ملف آخر بتغيير الامتداد.
     */
    public const ALLOWED_PRESENTATION_MIMES = [
        'ppt',
        'pptx',
    ];

    public const MAX_PRESENTATION_SIZE = 10240;

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'role_description'             => ['required', 'string', 'max:20000'],
            'summary'                      => ['required', 'string', 'max:20000'],

            // مرفق اختياري يُخزَّن في مجموعة final_file الموجودة على التسجيل.
            'final_file'                   => [
                'nullable',
                'file',
                'mimes:' . implode(',', self::ALLOWED_ATTACHMENT_MIMES),
                'max:' . self::MAX_ATTACHMENT_SIZE,
            ],

            // العرض التقديمي إجباري، ويُطلب مرة واحدة فقط: إن كان مرفوعاً من
            // قبل يبقى الحقل اختيارياً حتى لا يُعاد رفعه مع كل تعديل.
            'final_presentation'           => [
                'nullable',
                Rule::requiredIf(fn (): bool => ! $this->presentationAlreadyUploaded()),
                'file',
                'mimes:' . implode(',', self::ALLOWED_PRESENTATION_MIMES),
                'max:' . self::MAX_PRESENTATION_SIZE,
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

    public function attributes(): array
    {
        return [
            'role_description'            => __('Training Role Description'),
            'summary'                     => __('Summary'),
            'final_file'                  => __('Attachment'),
            'final_presentation'          => __('Presentation File'),
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

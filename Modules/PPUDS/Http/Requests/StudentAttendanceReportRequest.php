<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StudentAttendanceReportRequest extends FormRequest
{
    public const ALLOWED_REPORT_FILE_MIMES = [
        'pdf',
        'doc',
        'docx',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
    ];

    public const MAX_REPORT_FILE_SIZE = 10240;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fileRules = $this->reportFileRules();
        $fileReport = $this->file('file_report');

        return [
            'student_attendance_id' => ['required', 'integer', Rule::exists('ppu_ds_student_attendances', 'id')],
            'report_text'           => ['nullable', 'string'],
            'company_feedback'      => ['nullable', 'string'],
            'academic_feedback'     => ['nullable', 'string'],
            'submit_latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'submit_longitude'      => ['nullable', 'numeric', 'between:-180,180'],
            'file_report'           => is_array($fileReport)
                ? ['nullable', 'array', 'max:5']
                : ['nullable', ...$fileRules],
            'file_report.*'         => $fileRules,
        ];
    }

    public function messages(): array
    {
        $maxSizeMb = (int) (self::MAX_REPORT_FILE_SIZE / 1024);
        $typeMessage = __('The attached file must be a PDF, Word document (doc, docx), or image (jpg, jpeg, png, webp, gif).');
        $sizeMessage = __('The attached file may not be larger than :size.', ['size' => $maxSizeMb.'MB']);
        $invalidMessage = __('One of the attached files is invalid.');

        // "file_report" is validated as a single file's own rules when one file is sent,
        // but as an array-count rule when multiple files are sent (see rules()) — the
        // same key needs different wording depending on which case is active.
        $fileReportKeyMessages = is_array($this->file('file_report'))
            ? ['file_report.max' => __('You can attach a maximum of 5 files.')]
            : [
                'file_report.file'  => $invalidMessage,
                'file_report.mimes' => $typeMessage,
                'file_report.max'   => $sizeMessage,
            ];

        return $fileReportKeyMessages + [
            'file_report.*.file'  => $invalidMessage,
            'file_report.*.mimes' => $typeMessage,
            'file_report.*.max'   => $sizeMessage,
        ];
    }

    public function attributes(): array
    {
        return [
            'file_report'   => __('attached file'),
            'file_report.*' => __('attached file'),
        ];
    }

    public function reportFiles(): array
    {
        $files = $this->file('file_report', []);

        if (! is_array($files)) {
            $files = [$files];
        }

        return array_values(array_filter(
            $files,
            fn ($file): bool => $file instanceof UploadedFile
        ));
    }

    protected function reportFileRules(): array
    {
        return [
            'file',
            'mimes:'.implode(',', self::ALLOWED_REPORT_FILE_MIMES),
            'max:'.self::MAX_REPORT_FILE_SIZE,
        ];
    }
}

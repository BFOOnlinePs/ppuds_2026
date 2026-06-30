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

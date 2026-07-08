<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class FieldVisitUpdate extends FormRequest
{
    public const ALLOWED_ATTACHMENT_MIMES = FieldVisitRequest::ALLOWED_ATTACHMENT_MIMES;
    public const MAX_ATTACHMENT_SIZE = FieldVisitRequest::MAX_ATTACHMENT_SIZE;

    public function rules(): array
    {
        $fileRules = $this->attachmentRules();
        $optionalFileRules = ['sometimes', 'nullable', ...$fileRules];
        $attachments = $this->file('attachments');
        $misspelledAttachments = $this->file('attachemnts');
        $images = $this->file('images');

        return [
            'student_company_id' => ['sometimes', 'exists:' . config('ppuds.table_prefix') . 'students_companies,id'],
            'supervisor_id'      => ['sometimes', 'exists:users,id'],
            'visiting_place'     => ['sometimes', 'string', 'max:255'],
            'visit_date'         => ['sometimes', 'date'],
            'visit_time'         => ['sometimes', 'date_format:H:i:s'],
            'visit_duration'     => ['sometimes', 'integer', 'min:1'],
            'notes'              => ['sometimes', 'nullable', 'string'],
            'attachment'         => $optionalFileRules,
            'image'              => $optionalFileRules,
            'attachments'        => is_array($attachments) ? ['sometimes', 'nullable', 'array', 'max:10'] : $optionalFileRules,
            'attachments.*'      => $fileRules,
            'attachemnts'        => is_array($misspelledAttachments) ? ['sometimes', 'nullable', 'array', 'max:10'] : $optionalFileRules,
            'attachemnts.*'      => $fileRules,
            'images'             => is_array($images) ? ['sometimes', 'nullable', 'array', 'max:10'] : $optionalFileRules,
            'images.*'           => $fileRules,
        ];
    }

    public function attachmentFiles(): array
    {
        $files = [
            $this->file('attachment'),
            ...$this->normalizeFiles($this->file('attachments', [])),
            ...$this->normalizeFiles($this->file('attachemnts', [])),
            $this->file('image'),
            ...$this->normalizeFiles($this->file('images', [])),
        ];

        return array_values(array_filter(
            $files,
            fn ($file): bool => $file instanceof UploadedFile
        ));
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function attachmentRules(): array
    {
        return [
            'file',
            'mimes:' . implode(',', self::ALLOWED_ATTACHMENT_MIMES),
            'max:' . self::MAX_ATTACHMENT_SIZE,
        ];
    }

    private function normalizeFiles(mixed $files): array
    {
        return is_array($files) ? $files : [$files];
    }
}

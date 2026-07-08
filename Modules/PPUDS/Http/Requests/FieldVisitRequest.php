<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class FieldVisitRequest extends FormRequest
{
    public const ALLOWED_ATTACHMENT_MIMES = [
        'pdf',
        'doc',
        'docx',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'heic',
        'heif',
    ];

    public const MAX_ATTACHMENT_SIZE = 10240;

    public function rules(): array
    {
        $fileRules = $this->attachmentRules();
        $attachments = $this->file('attachments');
        $misspelledAttachments = $this->file('attachemnts');
        $images = $this->file('images');

        return [
            'student_company_id' => ['required', 'exists:' . config('ppuds.table_prefix') . 'students_companies,id'],
            'supervisor_id'      => ['required', 'exists:users,id'],
            'visiting_place'     => ['required', 'string', 'max:255'],
            'visit_date'         => ['required', 'date'],
            'visit_time'         => ['required', 'date_format:H:i:s'],
            'visit_duration'     => ['required', 'integer', 'min:1'],
            'attachment'         => ['nullable', ...$fileRules],
            'image'              => ['nullable', ...$fileRules],
            'attachments'        => is_array($attachments) ? ['nullable', 'array', 'max:10'] : ['nullable', ...$fileRules],
            'attachments.*'      => $fileRules,
            'attachemnts'        => is_array($misspelledAttachments) ? ['nullable', 'array', 'max:10'] : ['nullable', ...$fileRules],
            'attachemnts.*'      => $fileRules,
            'images'             => is_array($images) ? ['nullable', 'array', 'max:10'] : ['nullable', ...$fileRules],
            'images.*'           => $fileRules,
            'notes'              => ['nullable', 'string'],
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
        if (! is_array($files)) {
            return [$files];
        }

        $normalized = [];

        foreach ($files as $file) {
            array_push($normalized, ...$this->normalizeFiles($file));
        }

        return $normalized;
    }
}

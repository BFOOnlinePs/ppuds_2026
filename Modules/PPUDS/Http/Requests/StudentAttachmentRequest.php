<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StudentAttachmentRequest extends FormRequest
{
    public const ALLOWED_ATTACHMENT_MIMES = [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
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

        return [
            'student_id'    => ['nullable', 'integer', 'exists:users,id'],
            'name'          => ['nullable', 'string', 'max:255'],
            'attachment'    => ['required_without:attachments', 'nullable', ...$fileRules],
            'attachments'   => is_array($attachments)
                ? ['required_without:attachment', 'nullable', 'array', 'max:10']
                : ['required_without:attachment', 'nullable', ...$fileRules],
            'attachments.*' => $fileRules,
        ];
    }

    public function attachmentFiles(): array
    {
        $files = [
            $this->file('attachment'),
            ...$this->normalizeFiles($this->file('attachments', [])),
        ];

        return array_values(array_filter(
            $files,
            fn ($file): bool => $file instanceof UploadedFile
        ));
    }

    public function studentId(): int
    {
        return (int) ($this->input('student_id') ?: auth()->id());
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function attachmentRules(): array
    {
        return [
            'file',
            'mimes:'.implode(',', self::ALLOWED_ATTACHMENT_MIMES),
            'max:'.self::MAX_ATTACHMENT_SIZE,
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

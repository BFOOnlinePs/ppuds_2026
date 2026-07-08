<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\StudentCompany;

class BulkFieldVisitRequest extends FormRequest
{
    public function rules(): array
    {
        $fileRules = $this->attachmentRules();
        $attachments = $this->file('attachments');
        $images = $this->file('images');

        return [
            'company_id' => ['required', 'integer', Rule::exists((new Company)->getTable(), 'id')],
            'student_company_ids' => ['required', 'array', 'min:1', 'max:100'],
            'student_company_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists((new StudentCompany)->getTable(), 'id'),
            ],
            'supervisor_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'visiting_place' => ['required', 'string', 'max:255'],
            'visit_date' => ['required', 'date'],
            'visit_time' => ['required', 'date_format:H:i:s'],
            'visit_duration' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', ...$fileRules],
            'image' => ['nullable', ...$fileRules],
            'attachments' => is_array($attachments) ? ['nullable', 'array', 'max:10'] : ['nullable', ...$fileRules],
            'attachments.*' => $fileRules,
            'images' => is_array($images) ? ['nullable', 'array', 'max:10'] : ['nullable', ...$fileRules],
            'images.*' => $fileRules,
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function studentCompanyIds(): array
    {
        return collect($this->validated('student_company_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function attachmentFiles(): array
    {
        $files = [
            $this->file('attachment'),
            $this->file('image'),
            ...$this->normalizeFiles($this->file('attachments', [])),
            ...$this->normalizeFiles($this->file('images', [])),
        ];

        return array_values(array_filter(
            $files,
            fn ($file): bool => $file instanceof UploadedFile
        ));
    }

    protected function attachmentRules(): array
    {
        return [
            'file',
            'mimes:' . implode(',', FieldVisitRequest::ALLOWED_ATTACHMENT_MIMES),
            'max:' . FieldVisitRequest::MAX_ATTACHMENT_SIZE,
        ];
    }

    private function normalizeFiles(mixed $files): array
    {
        return is_array($files) ? $files : [$files];
    }
}

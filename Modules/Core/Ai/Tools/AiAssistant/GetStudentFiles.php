<?php

namespace Modules\Core\Ai\Tools\AiAssistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Modules\PPUDS\Entities\StudentProfile;
use Stringable;

class GetStudentFiles extends AiAssistantTool
{
    public function description(): Stringable|string
    {
        return 'عرض ملفات الطالب: السيرة الذاتية CV ومرفقات الطالب مع روابط فتحها.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->userCan('Student Details List')) {
            return $this->denied('Student Details List');
        }

        $student = $this->accessibleStudent($request->integer('student_id'));

        if (! $student) {
            return $this->studentNotFound();
        }

        /** @var StudentProfile $profile */
        $profile = $student->studentProfile;
        $cv = $profile->getFirstMedia('cv');

        return $this->respond([
            'ok' => true,
            'student' => $this->studentSummary($student),
            'cv' => $cv ? [
                'name' => $cv->name ?: $cv->file_name,
                'url' => $cv->getUrl(),
                'size' => $cv->human_readable_size,
            ] : null,
            'cv_status' => $profile->cv_status,
            'attachments' => $profile->getAttachmentItems()
                ->map(fn (array $item) => [
                    'name' => $item['name'] ?: $item['file_name'],
                    'url' => $item['url'],
                    'size' => $item['human_readable_size'],
                    'mime_type' => $item['mime_type'],
                ])
                ->values()
                ->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('student_id من SearchStudents')->required(),
        ];
    }
}

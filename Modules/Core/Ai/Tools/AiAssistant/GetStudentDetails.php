<?php

namespace Modules\Core\Ai\Tools\AiAssistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Modules\Core\Services\StudentTrainingChatService;
use Stringable;

class GetStudentDetails extends AiAssistantTool
{
    public function description(): Stringable|string
    {
        return 'عرض كل معلومات الطالب: بياناته الشخصية والأكاديمية، مدينة السكن، التسجيلات، شركة التدريب والفرع والقسم، الحضور، طلبات الغياب، الزيارات الميدانية والتقارير.';
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

        $payload = app(StudentTrainingChatService::class)->payloadForStudent($student->id);

        return $this->respond([
            'ok' => true,
            'student' => $this->studentSummary($student),
            'data' => $payload,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('student_id من SearchStudents')->required(),
        ];
    }
}

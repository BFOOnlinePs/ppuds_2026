<?php

namespace Modules\Core\Ai\Tools\AiAssistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Modules\Core\Actions\StudentCompanyAssistant\ResolveStudentCompanyRegistration;
use Modules\Core\Services\StudentCompanySuggestionService;
use Stringable;

class SuggestCompaniesForStudent extends AiAssistantTool
{
    public function description(): Stringable|string
    {
        return 'اقتراح شركات مناسبة للطالب حسب تخصصه ومساقه وعدد الطلاب الحاليين في كل شركة (بدون اعتبار المسافة).';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->userCan('StudentCompany Create')) {
            return $this->denied('StudentCompany Create');
        }

        $student = $this->accessibleStudent($request->integer('student_id'));

        if (! $student) {
            return $this->studentNotFound();
        }

        [$registration, $warning] = app(ResolveStudentCompanyRegistration::class)->handle($student);

        if (! $registration) {
            return $this->error($warning ?: 'لا يوجد للطالب تسجيل في الفصل الحالي.');
        }

        $result = app(StudentCompanySuggestionService::class)->suggest($student, $registration);

        return $this->respond([
            'ok' => true,
            'student' => $this->studentSummary($student),
            'summary' => $result['message'],
            'suggestions' => $result['suggestions'],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('student_id من SearchStudents')->required(),
        ];
    }
}

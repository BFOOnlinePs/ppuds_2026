<?php

namespace Modules\Core\Ai\Tools\AiAssistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Modules\Core\Actions\AiAssistant\UnlinkStudentCompany;
use Modules\Core\Actions\StudentCompanyAssistant\ResolveStudentCompanyRegistration;
use Modules\PPUDS\Entities\StudentCompany;
use Stringable;

class UnlinkStudentFromCompany extends AiAssistantTool
{
    public function description(): Stringable|string
    {
        return 'إلغاء ربط الطالب بشركة تدريب في الفصل الحالي. company_id اختياري، وبدونه يُلغى آخر ربط. عملية حساسة: نفّذها فقط بطلب صريح من المستخدم.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->userCan('StudentCompany Delete')) {
            return $this->denied('StudentCompany Delete');
        }

        $student = $this->accessibleStudent($request->integer('student_id'));

        if (! $student) {
            return $this->studentNotFound();
        }

        [$registration, $warning] = app(ResolveStudentCompanyRegistration::class)->handle($student);

        if (! $registration) {
            return $this->error($warning ?: 'لا يوجد للطالب تسجيل في الفصل الحالي.');
        }

        $studentCompany = StudentCompany::query()
            ->with('company')
            ->where('student_id', $student->id)
            ->where('registration_id', $registration->id)
            ->whereNotNull('company_id')
            ->when($request->integer('company_id'), fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->latest('id')
            ->first();

        if (! $studentCompany) {
            return $this->error("لا يوجد ربط حالي مطابق للطالب {$student->name} في الفصل الحالي.");
        }

        $companyName = $studentCompany->company?->name ?: 'الشركة';

        app(UnlinkStudentCompany::class)->handle($studentCompany);

        return $this->respond([
            'ok' => true,
            'message' => "تم إلغاء ربط الطالب {$student->name} بشركة {$companyName}.",
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('student_id من SearchStudents')->required(),
            'company_id' => $schema->integer()->description('اختياري: الشركة المراد إلغاء ربطها'),
        ];
    }
}

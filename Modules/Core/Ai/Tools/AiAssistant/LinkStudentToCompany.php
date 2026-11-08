<?php

namespace Modules\Core\Ai\Tools\AiAssistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Modules\Core\Actions\StudentCompanyAssistant\FindCompaniesForCompanyAssistant;
use Modules\Core\Actions\StudentCompanyAssistant\LinkSuggestedCompanyToStudent;
use Modules\Core\Actions\StudentCompanyAssistant\ResolveStudentCompanyRegistration;
use Stringable;

class LinkStudentToCompany extends AiAssistantTool
{
    public function description(): Stringable|string
    {
        return 'تسجيل (ربط) الطالب في شركة تدريب ضمن تسجيله في الفصل الحالي. branch_id و department_id اختياريان، وإن لم يُمرّرا يُختار أول فرع وقسم للشركة.';
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

        $findCompanies = app(FindCompaniesForCompanyAssistant::class);
        $company = $findCompanies->find($request->integer('company_id'));

        if (! $company) {
            return $this->error('لم أجد هذه الشركة. استخدم SearchCompanies للحصول على company_id الصحيح.');
        }

        [$registration, $warning] = app(ResolveStudentCompanyRegistration::class)->handle($student);

        if (! $registration) {
            return $this->error($warning ?: 'لا يوجد للطالب تسجيل في الفصل الحالي، لذلك لا يمكن ربطه بشركة.');
        }

        $suggestion = $findCompanies->toSuggestion($company, 'تم الربط عبر مساعد الذكاء الاصطناعي.');
        $suggestion['branch_id'] = $request->integer('branch_id') ?: $suggestion['branch_id'];
        $suggestion['department_id'] = $request->integer('department_id') ?: $suggestion['department_id'];

        $result = app(LinkSuggestedCompanyToStudent::class)->handle(
            $student->id,
            $registration->id,
            $suggestion,
            (int) auth()->id(),
        );

        return $this->respond([
            'ok' => $result !== 'already_exists',
            'result' => $result,
            'message' => match ($result) {
                'already_exists' => "الطالب {$student->name} مسجل مسبقًا في شركة {$company->name}، لم يتم إنشاء ربط جديد.",
                'updated' => "تمت إعادة ربط الطالب {$student->name} بشركة {$company->name}.",
                default => "تم تسجيل الطالب {$student->name} في شركة {$company->name}.",
            },
            'student' => $this->studentSummary($student),
            'company_name' => $company->name,
            'registration' => trim(($registration->course?->name ?? '-').' - '.($registration->semester?->getLabel() ?? $registration->semester?->value).'/'.$registration->year),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('student_id من SearchStudents')->required(),
            'company_id' => $schema->integer()->description('company_id من SearchCompanies أو الاقتراحات')->required(),
            'branch_id' => $schema->integer()->description('اختياري: فرع الشركة'),
            'department_id' => $schema->integer()->description('اختياري: القسم داخل الفرع'),
        ];
    }
}

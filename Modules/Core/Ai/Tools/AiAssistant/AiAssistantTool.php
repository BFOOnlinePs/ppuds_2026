<?php

namespace Modules\Core\Ai\Tools\AiAssistant;

use Laravel\Ai\Contracts\Tool;
use Modules\Core\Entities\User;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

/**
 * أساس أدوات مساعد الذكاء الاصطناعي.
 * كل أداة تتحقق من صلاحية المستخدم الحالي ومن حقه في رؤية الطالب قبل التنفيذ،
 * لأن الذكاء الاصطناعي لا يُعتبر مصدر ثقة.
 */
abstract class AiAssistantTool implements Tool
{
    use ScopesStudentCompanyVisibility;

    protected function userCan(string $permission): bool
    {
        return (bool) auth()->user()?->can($permission);
    }

    protected function denied(string $permission): string
    {
        return $this->error("لا يملك المستخدم الحالي صلاحية ({$permission}) لتنفيذ هذا الطلب. أخبر المستخدم بذلك ولا تحاول بطريقة أخرى.");
    }

    protected function accessibleStudent(int $studentId): ?User
    {
        if ($studentId <= 0 || ! $this->canAccessStudentUser($studentId)) {
            return null;
        }

        return User::query()
            ->with(['studentProfile.major', 'studentProfile.residenceCity'])
            ->whereHas('studentProfile')
            ->find($studentId);
    }

    protected function studentNotFound(): string
    {
        return $this->error('لم أجد هذا الطالب أو لا يملك المستخدم صلاحية الوصول إليه. استخدم SearchStudents للحصول على student_id الصحيح.');
    }

    protected function studentSummary(User $student): array
    {
        return [
            'student_id' => $student->id,
            'name' => $student->name,
            'student_number' => $student->studentProfile?->student_number,
            'major' => $student->studentProfile?->major?->name,
            'residence_city' => $student->studentProfile?->residenceCity?->name,
            'details_url' => route('students.details', $student->id),
        ];
    }

    protected function respond(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function error(string $message): string
    {
        return $this->respond(['ok' => false, 'error' => $message]);
    }
}

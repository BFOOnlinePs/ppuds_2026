<?php

namespace Modules\Core\Ai\Tools\AiAssistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Modules\Core\Actions\StudentCompanyAssistant\FindStudentsForCompanyAssistant;
use Modules\Core\Entities\User;
use Stringable;

class SearchStudents extends AiAssistantTool
{
    public function description(): Stringable|string
    {
        return 'البحث عن طالب بالاسم العربي أو الإنجليزي أو الرقم الجامعي أو البريد. يُرجع student_id الذي تحتاجه بقية الأدوات.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->userCan('Student View List')) {
            return $this->denied('Student View List');
        }

        $query = trim((string) $request->string('query'));

        if (mb_strlen($query) < 2) {
            return $this->error('اكتب حرفين على الأقل للبحث.');
        }

        $students = app(FindStudentsForCompanyAssistant::class)
            ->handle($query, 10)
            ->load('studentProfile.residenceCity')
            ->filter(fn (User $student) => $this->canAccessStudentUser($student->id))
            ->values();

        return $this->respond([
            'ok' => true,
            'count' => $students->count(),
            'students' => $students->map(fn (User $student) => $this->studentSummary($student))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('اسم الطالب أو رقمه الجامعي أو بريده')->required(),
        ];
    }
}

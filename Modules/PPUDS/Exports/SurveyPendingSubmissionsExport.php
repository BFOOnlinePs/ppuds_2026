<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Support\HandlesCompanySupervisorSurveyEvaluations;

class SurveyPendingSubmissionsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    use HandlesCompanySupervisorSurveyEvaluations;

    /**
     * $query يأتي من جدول "بانتظار التسليم" بعد تطبيق الفلاتر والبحث، فيخرج
     * الملف مطابقاً لما يراه المستخدم على الشاشة.
     */
    public function __construct(protected Survey $survey, protected Builder $query) {}

    public function headings(): array
    {
        return $this->isCompanySupervisorSurvey($this->survey)
            ? [
                __('Evaluated Student'),
                __('Email'),
                __('Student Number'),
                __('Major'),
                __('Company'),
                __('Branch'),
                __('Department'),
                __('Status'),
            ]
            : [
                __('Name'),
                __('Email'),
                __('Phone'),
                __('Student Number'),
                __('Major'),
                __('Target Group'),
                __('Status'),
            ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        if ($this->isCompanySupervisorSurvey($this->survey)) {
            $query->with([
                'student.studentProfile.major.translations',
                'company.translations',
                'branch.translations',
                'department.translations',
            ]);

            foreach ($query->lazy(500) as $studentCompany) {
                yield $this->rowForStudentCompany($studentCompany);
            }

            return;
        }

        $query->with(['studentProfile.major.translations']);

        foreach ($query->lazy(500) as $user) {
            yield $this->rowForUser($user);
        }
    }

    protected function rowForUser(User $user): array
    {
        return [
            (string) $user->name,
            (string) $user->email,
            (string) $user->phone,
            (string) $user->studentProfile?->student_number,
            (string) $user->studentProfile?->major?->name,
            $this->targetGroupLabel(),
            __('Not Submitted'),
        ];
    }

    protected function rowForStudentCompany(StudentCompany $studentCompany): array
    {
        $student = $studentCompany->student;

        return [
            (string) $student?->name,
            (string) $student?->email,
            (string) $student?->studentProfile?->student_number,
            (string) $student?->studentProfile?->major?->name,
            (string) $studentCompany->company?->name,
            (string) $studentCompany->branch?->name,
            (string) $studentCompany->department?->name,
            __('Not Submitted'),
        ];
    }

    protected function targetGroupLabel(): string
    {
        $role = $this->survey->serve_group;

        return $role
            ? UserRole::tryFrom($role)?->getLabel() ?? $role
            : '-';
    }
}

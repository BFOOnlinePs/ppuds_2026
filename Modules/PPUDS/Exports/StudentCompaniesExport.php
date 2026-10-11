<?php

namespace Modules\PPUDS\Exports;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;

class StudentCompaniesExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    protected bool $showEvaluationSupervisor;

    public function __construct(protected Builder $query)
    {
        // نفس شرط ظهور عمود مشرف التقييم في الجدول: لا يراه الطالب ولا مشرف الشركة.
        $this->showEvaluationSupervisor = (bool) (auth()->user()?->can('StudentCompany Assign Evaluation Supervisor')
            || auth()->user()?->can('EvaluationSupervisor View List'));
    }

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student Name'),
            __('Email'),
            __('Phone'),
            __('Major'),
            __('Company'),
            __('Branch'),
            __('Department'),
            __('Status'),
            __('Course'),
            __('Semester'),
            __('Year'),
            __('University Supervisor'),
            __('Company Supervisor'),
            ...($this->showEvaluationSupervisor ? [__('Evaluation Supervisor')] : []),
            __('Attendance Days'),
            __('Reports'),
            __('Supervisor Visit Days'),
            __('Leave Requests'),
            __('Actual Working Hours'),
            __('Created By'),
            __('Created At'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'branch.translations',
            'branch.supervisors',
            'company.translations',
            'department.translations',
            'registration.course.translations',
            'registration.student.studentProfile.major.translations',
            'registration.supervisor',
            'student.studentProfile.major.translations',
            'evaluationSupervisor',
            'createdBy',
        ])->withCount([
            'attendances as reports_count' => fn (Builder $attendanceQuery) => $attendanceQuery->has('studentReport'),
            'leaveRequests',
        ]);

        foreach ($query->lazy(500) as $studentCompany) {
            yield $this->rowFor($studentCompany);
        }
    }

    protected function rowFor(StudentCompany $studentCompany): array
    {
        $registration = $studentCompany->registration;
        $student = $studentCompany->student ?? $registration?->student;
        $studentProfile = $student?->studentProfile;

        return [
            (string) $studentProfile?->student_number,
            (string) $student?->name,
            (string) $student?->email,
            (string) $student?->phone,
            (string) $studentProfile?->major?->name,
            (string) $studentCompany->company?->name,
            (string) $studentCompany->branch?->name,
            (string) $studentCompany->department?->name,
            $this->statusLabel($studentCompany->status),
            (string) $registration?->course?->name,
            $this->semesterLabel($registration?->semester),
            (string) $registration?->year,
            (string) $registration?->supervisor?->name,
            (string) $this->companySupervisorFor($studentCompany)?->name,
            ...($this->showEvaluationSupervisor ? [(string) $studentCompany->evaluationSupervisor?->name] : []),
            (int) ($studentCompany->attendance_days ?? 0),
            (int) ($studentCompany->reports_count ?? 0),
            (int) ($studentCompany->field_visit_days ?? 0),
            (int) ($studentCompany->leave_requests_count ?? 0),
            (string) $studentCompany->actual_working_hours,
            (string) $studentCompany->createdBy?->name,
            $this->dateTimeValue($studentCompany->created_at),
        ];
    }

    /**
     * مشرف الشركة هو من يشغل مقعد (الفرع + القسم) في branch_department،
     * بنفس قاعدة عمود مشرف الشركة في جدول التدريبات.
     */
    protected function companySupervisorFor(StudentCompany $studentCompany): ?User
    {
        if (! $studentCompany->department_id) {
            return null;
        }

        return $studentCompany->branch?->supervisors
            ->first(fn (User $supervisor): bool => (int) $supervisor->pivot->company_department_id === (int) $studentCompany->department_id);
    }

    protected function statusLabel(mixed $status): string
    {
        if ($status instanceof TrainingStatus) {
            return (string) $status->getLabel();
        }

        if (is_numeric($status)) {
            return (string) (TrainingStatus::tryFrom((int) $status)?->getLabel() ?? $status);
        }

        return (string) $status;
    }

    protected function semesterLabel(mixed $semester): string
    {
        if ($semester instanceof SemesterType) {
            return (string) $semester->getLabel();
        }

        if (is_numeric($semester)) {
            return (string) (SemesterType::tryFrom((int) $semester)?->getLabel() ?? $semester);
        }

        return (string) $semester;
    }

    protected function dateTimeValue(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i') : (string) $value;
    }
}

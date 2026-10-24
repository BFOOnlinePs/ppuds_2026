<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Settings\GeneralSettings;

/**
 * تصدير شاشة طلاب مشرف التقييم إلى إكسل.
 *
 * الاستعلام يصل من الشاشة وقد قُيّد بطلاب المشرف الحالي وبالفلاتر، فلا يكرر
 * هذا الصنف منطق الرؤية. ويحمل نفس أعمدة الشاشة وحدها: علامة مشرف التقييم دون
 * علامتي مشرف الجامعة والشركة، لأن مشرف التقييم لا يملك صلاحية شاشة العلامات.
 *
 * العلامة تُكتب رقماً لتبقى قابلة للجمع والفرز، وتبقى الخانة فارغة لمن لم
 * تُرصد علامته حتى لا يُقرأ الصفر علامةً.
 */
class EvaluationSupervisorStudentsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(
        protected Builder $query,
        protected bool $withEvaluationSupervisor = false,
    ) {}

    public function headings(): array
    {
        return array_values(array_filter([
            __('Student Number'),
            __('Student'),
            __('Major'),
            __('Company'),
            __('Department'),
            $this->withEvaluationSupervisor ? __('Evaluation Supervisor') : null,
            __('Evaluation Supervisor Grade').' ('.__('Out of :max', ['max' => app(GeneralSettings::class)->evaluation_supervisor_max_grade]).')',
            __('Attendance Days'),
            __('Attendance Hours'),
        ], fn ($heading): bool => $heading !== null));
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'company.translations',
            'department',
            'evaluationSupervisor',
            'student.studentProfile.major.translations',
        ]);

        foreach ($query->lazy(500) as $studentCompany) {
            yield $this->rowFor($studentCompany);
        }
    }

    protected function rowFor(StudentCompany $studentCompany): array
    {
        $studentProfile = $studentCompany->student?->studentProfile;

        $row = [
            (string) $studentProfile?->student_number,
            (string) $studentCompany->student?->name,
            (string) $studentProfile?->major?->name,
            (string) $studentCompany->company?->name,
            (string) $studentCompany->department?->name,
        ];

        if ($this->withEvaluationSupervisor) {
            $row[] = (string) $studentCompany->evaluationSupervisor?->name;
        }

        $row[] = $studentCompany->evaluation_score ?? '';
        $row[] = (int) $studentCompany->attendance_days;
        $row[] = round((float) $studentCompany->actual_working_hours, 2);

        return $row;
    }
}

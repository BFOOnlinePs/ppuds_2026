<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Transformers\V1\StudentGradeResource;

/**
 * تصدير شاشة العلامات إلى إكسل.
 *
 * لا يقرر هذا الصنف من يرى ماذا: الاستعلام يصل إليه وقد مرّ بـ
 * applyStudentCompanyVisibilityScope في الشاشة، فالأدمن يصدّر الجميع والمشرف
 * يصدّر طلابه وحدهم بنفس القاعدة التي تحكم الجدول — دون تكرار منطق الصلاحيات.
 *
 * العلامات تُكتب أرقاماً لا نصوصاً («18» لا «18 / 20») ليبقى الملف قابلاً
 * للجمع والفرز في إكسل، والعلامة القصوى في عنوان العمود. والخانة تبقى فارغة
 * لمن لم تُرصد علامته حتى لا يُقرأ الصفر علامةً.
 */
class StudentGradesExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        $settings = app(GeneralSettings::class);

        return [
            __('Student Number'),
            __('Student'),
            __('Major'),
            __('Company'),
            __('Branch'),
            __('Evaluation Supervisor'),
            __('Practical Training Supervisor'),
            $this->gradeHeading(__('Evaluation Supervisor Grade'), $settings->evaluation_supervisor_max_grade),
            $this->gradeHeading(__('University Supervisor Grade'), $settings->university_supervisor_max_grade),
            $this->gradeHeading(__('Company Grade'), $settings->company_max_grade),
            $this->gradeHeading(__('Total Grade'), $this->totalMaxGrade($settings)),
            __('Semester'),
            __('Academic Year'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'branch.translations',
            'company.translations',
            'evaluationSupervisor',
            'registration.supervisor',
            'student.studentProfile.major.translations',
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

        $evaluationScore = $studentCompany->evaluation_score;
        $supervisorScore = $studentCompany->supervisor_score;
        $companyScore = $this->companyGrade($studentCompany);

        return [
            (string) $studentProfile?->student_number,
            (string) $student?->name,
            (string) $studentProfile?->major?->name,
            (string) $studentCompany->company?->name,
            (string) $studentCompany->branch?->name,
            (string) $studentCompany->evaluationSupervisor?->name,
            (string) $registration?->supervisor?->name,
            $this->gradeValue($evaluationScore),
            $this->gradeValue($supervisorScore),
            $this->gradeValue($companyScore),
            $this->gradeValue(StudentGradeResource::totalScore($evaluationScore, $supervisorScore, $companyScore)),
            $this->semesterLabel($registration?->semester),
            (string) $registration?->year,
        ];
    }

    /**
     * علامة الشركة: المحسوبة من استبيان مشرف الشركة هي المصدر متى وُجدت، وإلا
     * فالقيمة المستوردة من مزامنة نظام الجامعة — نفس قاعدة الشاشة والـ API.
     */
    protected function companyGrade(StudentCompany $studentCompany): int|float|null
    {
        return $studentCompany->company_survey_score ?? $studentCompany->registration?->company_score;
    }

    protected function gradeValue(int|float|null $score): int|float|string
    {
        if ($score === null) {
            return '';
        }

        return (float) $score == (int) $score ? (int) $score : round((float) $score, 2);
    }

    protected function gradeHeading(string $label, int $maxGrade): string
    {
        return $label.' ('.__('Out of :max', ['max' => $maxGrade]).')';
    }

    protected function totalMaxGrade(GeneralSettings $settings): int
    {
        return $settings->evaluation_supervisor_max_grade
            + $settings->university_supervisor_max_grade
            + $settings->company_max_grade;
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
}

<?php

namespace Modules\PPUDS\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Enums\SurveyQuestionType;
use Modules\PPUDS\Settings\GeneralSettings;

/**
 * علامة الشركة محسوبة من استبيان مشرف الشركة: مجموع إجابات أسئلة التقييم
 * منسوباً إلى أعلى مجموع ممكن (عدد الأسئلة المُجابة × أعلى درجة في المقياس)،
 * مضروباً في علامة الشركة المقرّرة في إعدادات توزيع العلامات.
 */
class CompanyEvaluationGradeService
{
    /** علامة الشركة الكاملة كما هي في الإعدادات. */
    public function maxGrade(): int
    {
        return app(GeneralSettings::class)->company_max_grade;
    }

    /**
     * أعلى درجة في مقياس أسئلة التقييم، مقروءة من المقياس نفسه حتى لا تنفصل
     * القسمة عنه إن تغيّر.
     */
    public function ratingScaleMax(): int
    {
        return (int) max(array_keys(SurveyQuestionType::ratingScaleOptions()));
    }

    /** null إذا لم يُقيّم مشرف الشركة هذا الطالب بعد. */
    public function gradeFor(StudentCompany $studentCompany): ?float
    {
        return $this->gradesFor([$studentCompany->id])[$studentCompany->id] ?? null;
    }

    /**
     * يعيد الحساب ويثبّته على سجل الطالب في الشركة. يُستدعى بعد تسليم الاستبيان
     * من الشاشة ومن الـ API معاً حتى لا تختلف النتيجة بين المسارين.
     */
    public function refreshFor(StudentCompany $studentCompany): ?float
    {
        $grade = $this->gradeFor($studentCompany);

        $studentCompany->update(['company_survey_score' => $grade]);

        return $grade;
    }

    /**
     * نسخة الدفعة الواحدة: تمنع استعلاماً لكل صف في جداول العلامات.
     *
     * @param  array<int, int>  $studentCompanyIds
     * @return array<int, float> student_company_id => العلامة
     */
    public function gradesFor(array $studentCompanyIds): array
    {
        if ($studentCompanyIds === []) {
            return [];
        }

        $scaleMax = $this->ratingScaleMax();
        $maxGrade = $this->maxGrade();

        return $this->ratingAnswersQuery($studentCompanyIds)
            ->get(['student_company_id', 'text_answer'])
            ->groupBy('student_company_id')
            ->map(function (Collection $answers) use ($scaleMax, $maxGrade): float {
                $highestPossible = $answers->count() * $scaleMax;

                if ($highestPossible === 0) {
                    return 0.0;
                }

                $sum = $answers->sum(fn (SurveyAnswer $answer): int => (int) $answer->text_answer);

                return round($sum / $highestPossible * $maxGrade, 2);
            })
            ->all();
    }

    /**
     * إجابات أسئلة التقييم وحدها، ومن استبيانات مشرف الشركة وحدها. باقي أنواع
     * الأسئلة (نص، ملف، اختيار) لا تدخل في العلامة.
     *
     * @param  array<int, int>  $studentCompanyIds
     */
    protected function ratingAnswersQuery(array $studentCompanyIds): Builder
    {
        return SurveyAnswer::query()
            ->whereIn('student_company_id', $studentCompanyIds)
            ->whereNotNull('text_answer')
            ->whereHas(
                'survey',
                fn (Builder $query): Builder => $query->where('serve_group', UserRole::COMPANY_SUPERVISOR->value)
            )
            ->whereHas(
                'question',
                fn (Builder $query): Builder => $query->where('type', SurveyQuestionType::RATING->value)
            );
    }
}

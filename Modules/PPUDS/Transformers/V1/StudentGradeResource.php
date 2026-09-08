<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Transformers\V1\UserResource;
use Modules\PPUDS\Settings\GeneralSettings;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * علامات الطالب الثلاث ومجموعها. علامة مشرف التقييم وعلامة مشرف الجامعة
 * تُرصدان يدوياً وتُخزَّنان على students_companies، أما علامة الشركة فتُحسب من
 * استبيان مشرف الشركة (company_survey_score)، وترجع إلى القيمة المستوردة في
 * registrations.company_score لمن قُيّموا قبل تفعيل الاستبيان.
 *
 * @OA\Schema(
 * schema="StudentGrade",
 * title="Student Grade",
 * description="علامات الطالب الثلاث ومجموعها",
 * type="object",
 *
 * @OA\Property(property="student_company_id", type="integer", example=1),
 * @OA\Property(property="registration_id", type="integer", example=1),
 * @OA\Property(property="student_id", type="integer", example=12),
 * @OA\Property(property="student_name", type="string", example="أحمد محمد"),
 * @OA\Property(property="student_number", type="string", nullable=true, example="202012345"),
 * @OA\Property(property="major", type="string", nullable=true, example="هندسة الحاسوب"),
 * @OA\Property(property="company_id", type="integer", nullable=true, example=3),
 * @OA\Property(property="company_name", type="string", nullable=true, example="شركة التقنية"),
 * @OA\Property(property="branch_id", type="integer", nullable=true, example=2),
 * @OA\Property(property="branch_name", type="string", nullable=true, example="الفرع الرئيسي"),
 * @OA\Property(property="department_id", type="integer", nullable=true, example=4),
 * @OA\Property(property="department_name", type="string", nullable=true, example="التطوير"),
 * @OA\Property(property="evaluation_supervisor_id", type="integer", nullable=true, example=5),
 * @OA\Property(property="evaluation_supervisor_name", type="string", nullable=true, example="مشرف التقييم"),
 * @OA\Property(property="university_supervisor_id", type="integer", nullable=true, example=6),
 * @OA\Property(property="university_supervisor_name", type="string", nullable=true, example="مشرف الجامعة"),
 * @OA\Property(property="evaluation_score", type="integer", nullable=true, description="علامة مشرف التقييم، null إذا لم تُرصد", example=20),
 * @OA\Property(property="supervisor_score", type="integer", nullable=true, description="علامة مشرف الجامعة، null إذا لم تُرصد", example=30),
 * @OA\Property(property="company_score", type="number", format="float", nullable=true, description="علامة الشركة: محسوبة من استبيان مشرف الشركة، وإلا مزامَنة من نظام الجامعة", example=36),
 * @OA\Property(property="total_score", type="number", format="float", nullable=true, description="مجموع العلامات المرصودة، null إذا لم تُرصد أي علامة", example=86),
 * @OA\Property(property="max_evaluation_score", type="integer", example=25),
 * @OA\Property(property="max_supervisor_score", type="integer", example=35),
 * @OA\Property(property="max_company_score", type="integer", example=40),
 * @OA\Property(property="max_total_score", type="integer", example=100),
 * @OA\Property(property="is_fully_graded", type="boolean", example=false),
 * @OA\Property(property="semester", type="integer", nullable=true, example=1),
 * @OA\Property(property="year", type="integer", nullable=true, example=2026)
 * )
 */
class StudentGradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $settings = app(GeneralSettings::class);

        $evaluationScore = $this->evaluation_score;
        $supervisorScore = $this->supervisor_score;
        // علامة استبيان مشرف الشركة هي المصدر متى وُجدت، وإلا فالقيمة المستوردة.
        $companyScore = $this->company_survey_score ?? $this->registration?->company_score;

        return [
            'student_company_id' => $this->id,
            'registration_id' => $this->registration_id,
            'student_id' => $this->student_id,
            'student_name' => $this->student?->name,
            'student_number' => $this->student?->studentProfile?->student_number,
            'major' => $this->student?->studentProfile?->major?->name,

            'company_id' => $this->company_id,
            'company_name' => $this->company?->name,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,

            'evaluation_supervisor_id' => $this->evaluation_supervisor_id,
            'evaluation_supervisor_name' => $this->evaluationSupervisor?->name,
            'university_supervisor_id' => $this->registration?->supervisor_id,
            'university_supervisor_name' => $this->registration?->supervisor?->name,

            'evaluation_score' => $evaluationScore === null ? null : (int) $evaluationScore,
            'supervisor_score' => $supervisorScore === null ? null : (int) $supervisorScore,
            'company_score' => $companyScore === null ? null : (float) $companyScore,
            'total_score' => self::totalScore($evaluationScore, $supervisorScore, $companyScore),

            'max_evaluation_score' => $settings->evaluation_supervisor_max_grade,
            'max_supervisor_score' => $settings->university_supervisor_max_grade,
            'max_company_score' => $settings->company_max_grade,
            'max_total_score' => $settings->evaluation_supervisor_max_grade
                + $settings->university_supervisor_max_grade
                + $settings->company_max_grade,

            'is_fully_graded' => $evaluationScore !== null
                && $supervisorScore !== null
                && $companyScore !== null,

            'semester' => $this->registration?->semester,
            'year' => $this->registration?->year,

            'registration' => RegistrationResource::make($this->whenLoaded('registration')),
            'student' => UserResource::make($this->whenLoaded('student')),
            'company' => CompanyResource::make($this->whenLoaded('company')),
        ];
    }

    /**
     * المجموع يتجاهل العلامات غير المرصودة، ويبقى فارغاً ما لم تُرصد علامة
     * واحدة على الأقل حتى لا يظهر صفر لطالب لم يُقيَّم بعد.
     */
    public static function totalScore(mixed $evaluationScore, mixed $supervisorScore, mixed $companyScore): int|float|null
    {
        $recorded = array_filter(
            [$evaluationScore, $supervisorScore, $companyScore],
            fn ($score): bool => $score !== null
        );

        return $recorded === [] ? null : array_sum($recorded);
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'registration_id',
            'student_id',
            'company_id',
            'branch_id',
            'department_id',
            'evaluation_supervisor_id',
            'evaluation_score',
            'supervisor_score',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('registration_id'),
            AllowedFilter::exact('student_id'),
            AllowedFilter::exact('company_id'),
            AllowedFilter::exact('branch_id'),
            AllowedFilter::exact('evaluation_supervisor_id'),

            AllowedFilter::callback('university_supervisor_id', function (Builder $query, $value) {
                $query->whereHas('registration', function (Builder $registrationQuery) use ($value) {
                    $registrationQuery->where('supervisor_id', $value);
                });
            }),

            AllowedFilter::callback('student_number', function (Builder $query, $value) {
                $query->whereHas('student.studentProfile', function (Builder $profileQuery) use ($value) {
                    $profileQuery->where('student_number', 'like', '%'.$value.'%');
                });
            }),

            AllowedFilter::callback('student_name', function (Builder $query, $value) {
                $query->whereHas('student', function (Builder $studentQuery) use ($value) {
                    $studentQuery->where('name', 'like', '%'.$value.'%');
                });
            }),

            AllowedFilter::callback('search', function (Builder $query, $value) {
                $query->where(function (Builder $searchQuery) use ($value) {
                    $searchQuery
                        ->whereHas('student', function (Builder $studentQuery) use ($value) {
                            $studentQuery
                                ->where('name', 'like', '%'.$value.'%')
                                ->orWhere('email', 'like', '%'.$value.'%');
                        })
                        ->orWhereHas('student.studentProfile', function (Builder $profileQuery) use ($value) {
                            $profileQuery->where('student_number', 'like', '%'.$value.'%');
                        });
                });
            }),

            // graded / not_graded تُحسب على العلامة التي يملكها الطرف الطالب لها.
            AllowedFilter::callback('evaluation_graded', function (Builder $query, $value) {
                self::filterGraded($query, 'evaluation_score', $value);
            }),

            AllowedFilter::callback('supervisor_graded', function (Builder $query, $value) {
                self::filterGraded($query, 'supervisor_score', $value);
            }),

            AllowedFilter::callback('semester', function (Builder $query, $value) {
                $query->whereHas('registration', function (Builder $registrationQuery) use ($value) {
                    $registrationQuery->where('semester', $value);
                });
            }),

            AllowedFilter::callback('year', function (Builder $query, $value) {
                $query->whereHas('registration', function (Builder $registrationQuery) use ($value) {
                    $registrationQuery->where('year', $value);
                });
            }),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('created_at'),
            AllowedSort::field('evaluation_score'),
            AllowedSort::field('supervisor_score'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'registration',
            'registration.course',
            'registration.supervisor',
            'student',
            'student.studentProfile',
            'student.studentProfile.major',
            'evaluationSupervisor',
            'company',
            'branch',
            'department',
        ];
    }

    private static function filterGraded(Builder $query, string $column, mixed $value): void
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        filter_var($value, FILTER_VALIDATE_BOOLEAN)
            ? $query->whereNotNull($column)
            : $query->whereNull($column);
    }
}

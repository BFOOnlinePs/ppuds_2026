<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Settings\GeneralSettings;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class SurveyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'title' => $this->title,
            'description' => $this->description,
            'serve_group' => $this->serve_group,
            'major_id' => $this->major_id,
            'major' => $this->whenLoaded('major', fn () => [
                'id' => $this->major?->id,
                'name' => $this->major?->name,
            ]),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
            'semester' => $this->semester,
            'year' => $this->year,
            'is_submitted' => $this->isSubmittedFor($request),
            'questions' => SurveyQuestionResource::collection($this->whenLoaded('questions')),

            'created_at' => $this->created_at,
        ];
    }

    private function isSubmittedFor(Request $request): bool
    {
        $user = $request->user();

        if (! $user?->hasRole(UserRole::STUDENT->value) || $this->serve_group !== UserRole::STUDENT->value) {
            return (bool) $this->is_submitted;
        }

        $studentCompanyIds = $this->studentCompanyIdsForStudent((int) $user->id);

        if ($studentCompanyIds === []) {
            return (bool) $this->is_submitted;
        }

        $submittedStudentCompanies = SurveyAnswer::query()
            ->where('survey_id', $this->id)
            ->where('submitted_by', $user->id)
            ->whereIn('student_company_id', $studentCompanyIds)
            ->whereNotNull('student_company_id')
            ->distinct('student_company_id')
            ->count('student_company_id');

        return $submittedStudentCompanies >= count($studentCompanyIds);
    }

    private function studentCompanyIdsForStudent(int $studentId): array
    {
        $settings = app(GeneralSettings::class);

        return StudentCompany::query()
            ->where('student_id', $studentId)
            ->whereNotNull('company_id')
            ->whereHas('registration', function (Builder $query) use ($settings) {
                $query
                    ->where('semester', $settings->semester_type->value)
                    ->where('year', $settings->year);
            })
            ->when(
                $this->major_id,
                fn (Builder $query, int $majorId) => $query->whereHas(
                    'student.studentProfile',
                    fn (Builder $profileQuery) => $profileQuery->where('major_id', $majorId)
                )
            )
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public static function allowedFields(): array
    {
        return [
            'id', 'title', 'description',
            'serve_group', 'major_id', 'start_date', 'end_date', 'is_active', 'semester', 'year', 'created_at',
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('title', fn (Builder $query, $value) => $query->whereTranslationLike('title', "%{$value}%")),
            AllowedFilter::exact('serve_group'),
            AllowedFilter::exact('major_id'),
            AllowedFilter::exact('is_active'),
            AllowedFilter::exact('semester'),
            AllowedFilter::exact('year'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('title'),
            AllowedSort::field('created_at'),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'createdBy',
            'major',
            'questions',
            'questions.options',
        ];
    }
}

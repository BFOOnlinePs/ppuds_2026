<?php

namespace Modules\PPUDS\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Settings\GeneralSettings;

trait HandlesCompanySupervisorSurveyEvaluations
{
    protected function isCompanySupervisorSurvey(Survey $survey): bool
    {
        return $survey->serve_group === UserRole::COMPANY_SUPERVISOR->value;
    }

    protected function shouldEvaluateStudentsForSurvey(Survey $survey, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return $this->isCompanySupervisorSurvey($survey)
            && $user?->hasRole(UserRole::COMPANY_SUPERVISOR->value);
    }

    protected function shouldStudentEvaluateCompaniesForSurvey(Survey $survey, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return $survey->serve_group === UserRole::STUDENT->value
            && $user?->hasRole(UserRole::STUDENT->value)
            && $this->currentSurveyStudentCompaniesForStudentQuery($survey, (int) $user->id)->exists();
    }

    protected function currentSurveyStudentCompaniesQuery(?Survey $survey = null, ?int $supervisorUserId = null): Builder
    {
        $settings = app(GeneralSettings::class);
        $studentCompaniesTable = (new StudentCompany)->getTable();
        $pivotTable = config('ppuds.table_prefix').'branch_department';

        return StudentCompany::query()
            ->with([
                'student.studentProfile.major',
                'company.translations',
                'branch.translations',
                'department.translations',
                'registration',
            ])
            ->whereHas('registration', function (Builder $query) use ($settings) {
                $query
                    ->where('semester', $settings->semester_type->value)
                    ->where('year', $settings->year);
            })
            ->when(
                $survey?->major_id,
                fn (Builder $query, int $majorId) => $query->whereHas(
                    'student.studentProfile',
                    fn (Builder $profileQuery) => $profileQuery->where('major_id', $majorId)
                )
            )
            ->whereNotNull('branch_id')
            ->whereNotNull('department_id')
            ->whereExists(function ($query) use ($studentCompaniesTable, $pivotTable, $supervisorUserId) {
                $query
                    ->select(DB::raw(1))
                    ->from($pivotTable)
                    ->whereColumn("{$pivotTable}.branch_id", "{$studentCompaniesTable}.branch_id")
                    ->whereColumn("{$pivotTable}.company_department_id", "{$studentCompaniesTable}.department_id")
                    ->when(
                        $supervisorUserId,
                        fn ($pivotQuery) => $pivotQuery->where("{$pivotTable}.user_id", $supervisorUserId),
                        fn ($pivotQuery) => $pivotQuery->whereNotNull("{$pivotTable}.user_id")
                    );
            });
    }

    protected function pendingStudentCompaniesForSupervisorQuery(Survey $survey, int $supervisorUserId): Builder
    {
        $studentCompaniesTable = (new StudentCompany)->getTable();

        return $this->currentSurveyStudentCompaniesQuery($survey, $supervisorUserId)
            ->whereNotIn("{$studentCompaniesTable}.id", SurveyAnswer::query()
                ->select('student_company_id')
                ->where('survey_id', $survey->id)
                ->where('submitted_by', $supervisorUserId)
                ->whereNotNull('student_company_id')
                ->distinct()
            );
    }

    protected function currentSurveyStudentCompaniesForStudentQuery(Survey $survey, int $studentId): Builder
    {
        $settings = app(GeneralSettings::class);

        return StudentCompany::query()
            ->with([
                'student.studentProfile.major',
                'company.translations',
                'branch.translations',
                'department.translations',
                'registration',
            ])
            ->where('student_id', $studentId)
            ->whereNotNull('company_id')
            ->whereHas('registration', function (Builder $query) use ($settings) {
                $query
                    ->where('semester', $settings->semester_type->value)
                    ->where('year', $settings->year);
            })
            ->when(
                $survey->major_id,
                fn (Builder $query, int $majorId) => $query->whereHas(
                    'student.studentProfile',
                    fn (Builder $profileQuery) => $profileQuery->where('major_id', $majorId)
                )
            );
    }

    protected function pendingStudentCompaniesForStudentSurveyQuery(Survey $survey, int $studentId): Builder
    {
        $studentCompaniesTable = (new StudentCompany)->getTable();

        return $this->currentSurveyStudentCompaniesForStudentQuery($survey, $studentId)
            ->whereNotIn("{$studentCompaniesTable}.id", SurveyAnswer::query()
                ->select('student_company_id')
                ->where('survey_id', $survey->id)
                ->where('submitted_by', $studentId)
                ->whereNotNull('student_company_id')
                ->distinct()
            );
    }
}

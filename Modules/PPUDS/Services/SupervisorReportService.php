<?php

namespace Modules\PPUDS\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;

/**
 * Builds the supervisor report: one aggregated row per supervisor for the
 * summary view, and the underlying record sets for the detailed view.
 *
 * The aggregates are correlated sub-selects rather than eager-loaded
 * relations, so the summary table stays sortable and pageable in SQL even
 * when there are hundreds of supervisors.
 */
class SupervisorReportService
{
    /**
     * Every user who supervises, or has ever supervised, a registration.
     *
     * @param  array{year?: int|string|null, semester?: int|string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function supervisorsQuery(array $filters = []): Builder
    {
        return User::query()
            ->with(['roles', 'media'])
            ->where(fn (Builder $query): Builder => $query
                ->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('name', $this->supervisorRoleNames()))
                ->orWhereHas('supervisedRegistrations'))
            ->select('users.*')
            ->addSelect([
                'supervised_students_count' => $this->studentCompaniesSubQuery($filters, 'count(distinct {table}.student_id)'),
                'supervised_trainings_count' => $this->studentCompaniesSubQuery($filters, 'count(*)'),
                'supervised_companies_count' => $this->studentCompaniesSubQuery($filters, 'count(distinct {table}.company_id)'),
                'field_visits_count' => $this->fieldVisitsSubQuery($filters, 'count(*)'),
                'field_visit_minutes' => $this->fieldVisitsSubQuery($filters, 'coalesce(sum({table}.visit_duration), 0)'),
                'visited_students_count' => $this->fieldVisitsSubQuery($filters, 'count(distinct {table}.student_company_id)'),
                'last_field_visit_at' => $this->fieldVisitsSubQuery($filters, 'max({table}.visit_date)'),
                'activities_count' => $this->activitiesSubQuery($filters, 'count(*)'),
                'last_activity_at' => $this->activitiesSubQuery($filters, 'max({table}.created_at)'),
            ]);
    }

    /** Placements supervised by one supervisor, honouring the report filters. */
    public function studentCompaniesQuery(int $supervisorId, array $filters = []): Builder
    {
        return StudentCompany::query()
            ->whereHas('registration', function (Builder $query) use ($supervisorId, $filters): void {
                $query->where('supervisor_id', $supervisorId);

                $this->applyTermFilters($query, $filters);
            });
    }

    /** Field visits carried out by one supervisor, honouring the report filters. */
    public function fieldVisitsQuery(int $supervisorId, array $filters = []): Builder
    {
        return FieldVisit::query()
            ->where('supervisor_id', $supervisorId)
            ->when(
                filled($filters['date_from'] ?? null),
                fn (Builder $query): Builder => $query->whereDate('visit_date', '>=', $filters['date_from'])
            )
            ->when(
                filled($filters['date_to'] ?? null),
                fn (Builder $query): Builder => $query->whereDate('visit_date', '<=', $filters['date_to'])
            );
    }

    /** Everything the supervisor has done that the activity log recorded. */
    public function activitiesQuery(int $supervisorId, array $filters = []): Builder
    {
        return $this->activityModel()::query()
            ->where('causer_id', $supervisorId)
            ->where('causer_type', (new User)->getMorphClass())
            ->when(
                filled($filters['date_from'] ?? null),
                fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $filters['date_from'])
            )
            ->when(
                filled($filters['date_to'] ?? null),
                fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $filters['date_to'])
            );
    }

    /** The headline numbers shown above the detailed report. */
    public function summaryFor(int $supervisorId, array $filters = []): array
    {
        $studentCompanies = $this->studentCompaniesQuery($supervisorId, $filters);
        $fieldVisits = $this->fieldVisitsQuery($supervisorId, $filters);
        $activities = $this->activitiesQuery($supervisorId, $filters);

        return [
            'students' => (clone $studentCompanies)->distinct()->count('student_id'),
            'trainings' => (clone $studentCompanies)->count(),
            'companies' => (clone $studentCompanies)->whereNotNull('company_id')->distinct()->count('company_id'),
            'field_visits' => (clone $fieldVisits)->count(),
            'field_visit_minutes' => (int) (clone $fieldVisits)->sum('visit_duration'),
            'visited_students' => (clone $fieldVisits)->whereNotNull('student_company_id')->distinct()->count('student_company_id'),
            'last_field_visit_at' => (clone $fieldVisits)->max('visit_date'),
            'activities' => (clone $activities)->count(),
            'last_activity_at' => (clone $activities)->max('created_at'),
        ];
    }

    public function supervisorRoleNames(): array
    {
        return [
            UserRole::PRACTICAL_TRAINING_SUPERVISOR->value,
            'Academic Supervisor',
            'University Supervisor',
        ];
    }

    /**
     * Placement aggregate for the supervisor of the row being selected.
     *
     * `$aggregate` is a SQL expression such as `count(distinct {table}.student_id)`;
     * `{table}` stands for the placements table, and must be used for any
     * column the joined registrations table also has — both carry
     * `student_id`, so an unqualified name is ambiguous.
     */
    private function studentCompaniesSubQuery(array $filters, string $aggregate): QueryBuilder
    {
        $studentCompanies = (new StudentCompany)->getTable();
        $registrations = (new Registration)->getTable();

        return DB::table($studentCompanies)
            ->selectRaw(str_replace('{table}', $studentCompanies, $aggregate))
            ->join($registrations, "{$registrations}.id", '=', "{$studentCompanies}.registration_id")
            ->whereColumn("{$registrations}.supervisor_id", 'users.id')
            // Raw sub-queries bypass the models, so the soft-delete scopes
            // Eloquent would add have to be spelled out here.
            ->whereNull("{$studentCompanies}.deleted_at")
            ->whereNull("{$registrations}.deleted_at")
            ->when(
                filled($filters['year'] ?? null),
                fn (QueryBuilder $query): QueryBuilder => $query->where("{$registrations}.year", $filters['year'])
            )
            ->when(
                filled($filters['semester'] ?? null),
                fn (QueryBuilder $query): QueryBuilder => $query->where("{$registrations}.semester", $filters['semester'])
            );
    }

    private function fieldVisitsSubQuery(array $filters, string $aggregate): QueryBuilder
    {
        $fieldVisits = (new FieldVisit)->getTable();

        return DB::table($fieldVisits)
            ->selectRaw(str_replace('{table}', $fieldVisits, $aggregate))
            ->whereColumn("{$fieldVisits}.supervisor_id", 'users.id')
            ->whereNull("{$fieldVisits}.deleted_at")
            ->when(
                filled($filters['date_from'] ?? null),
                fn (QueryBuilder $query): QueryBuilder => $query->whereDate("{$fieldVisits}.visit_date", '>=', $filters['date_from'])
            )
            ->when(
                filled($filters['date_to'] ?? null),
                fn (QueryBuilder $query): QueryBuilder => $query->whereDate("{$fieldVisits}.visit_date", '<=', $filters['date_to'])
            );
    }

    private function activitiesSubQuery(array $filters, string $aggregate): QueryBuilder
    {
        $activities = $this->activityTable();

        return DB::table($activities)
            ->selectRaw(str_replace('{table}', $activities, $aggregate))
            ->whereColumn("{$activities}.causer_id", 'users.id')
            ->where("{$activities}.causer_type", (new User)->getMorphClass())
            ->when(
                filled($filters['date_from'] ?? null),
                fn (QueryBuilder $query): QueryBuilder => $query->whereDate("{$activities}.created_at", '>=', $filters['date_from'])
            )
            ->when(
                filled($filters['date_to'] ?? null),
                fn (QueryBuilder $query): QueryBuilder => $query->whereDate("{$activities}.created_at", '<=', $filters['date_to'])
            );
    }

    private function applyTermFilters(Builder $query, array $filters): void
    {
        $query
            ->when(
                filled($filters['year'] ?? null),
                fn (Builder $query): Builder => $query->where('year', $filters['year'])
            )
            ->when(
                filled($filters['semester'] ?? null),
                fn (Builder $query): Builder => $query->where('semester', $filters['semester'])
            );
    }

    /** @return class-string */
    public function activityModel(): string
    {
        return config('activitylog.activity_model', \Spatie\Activitylog\Models\Activity::class);
    }

    public function activityTable(): string
    {
        return config('activitylog.table_name', 'activity_log');
    }
}

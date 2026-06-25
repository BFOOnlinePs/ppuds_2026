<?php

namespace Modules\PPUDS\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\StudentCompany;

trait ScopesStudentCompanyVisibility
{
    protected function applyStudentCompanyVisibilityScope(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->currentUserIsAdmin()) {
            return $query;
        }

        if ($user->hasRole(UserRole::STUDENT->value)) {
            return $query->where('student_id', $user->id);
        }

        return $this->applyCompanySupervisorStudentCompanyScope($query);
    }

    protected function applyCompanySupervisorStudentCompanyScope(Builder $query): Builder
    {
        if (! $this->shouldScopeCompanySupervisorStudentCompanies()) {
            return $query;
        }

        return $this->applyStudentCompanySupervisorExistsScope($query, (int) auth()->id());
    }

    protected function applyStudentProfileVisibilityScope(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->currentUserIsAdmin()) {
            return $query;
        }

        if ($user->hasRole(UserRole::STUDENT->value)) {
            return $query->where('user_id', $user->id);
        }

        if ($this->shouldScopeCompanySupervisorStudentCompanies()) {
            return $query->whereHas(
                'user.studentCompanies',
                fn (Builder $studentCompanyQuery): Builder => $this->applyCompanySupervisorStudentCompanyScope($studentCompanyQuery)
            );
        }

        return $query;
    }

    protected function applyCompanyVisibilityScope(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->currentUserIsAdmin()) {
            return $query;
        }

        if ($user->hasRole(UserRole::STUDENT->value)) {
            return $query->whereHas(
                'studentCompanies',
                fn (Builder $studentCompanyQuery): Builder => $studentCompanyQuery->where('student_id', $user->id)
            );
        }

        if ($this->shouldScopeCompanySupervisorStudentCompanies()) {
            return $query->whereHas(
                'studentCompanies',
                fn (Builder $studentCompanyQuery): Builder => $this->applyCompanySupervisorStudentCompanyScope($studentCompanyQuery)
            );
        }

        return $query;
    }

    protected function canAccessStudentCompanyRecord(StudentCompany|int|null $studentCompany): bool
    {
        if (! $studentCompany || ! auth()->user()) {
            return false;
        }

        if ($this->currentUserIsAdmin()) {
            return true;
        }

        $studentCompanyId = $studentCompany instanceof StudentCompany
            ? $studentCompany->getKey()
            : $studentCompany;

        return $this->applyStudentCompanyVisibilityScope(
            StudentCompany::query()->whereKey($studentCompanyId)
        )->exists();
    }

    protected function canAccessStudentUser(int $studentUserId): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($this->currentUserIsAdmin()) {
            return true;
        }

        if ($user->hasRole(UserRole::STUDENT->value)) {
            return (int) $user->id === $studentUserId;
        }

        if ($this->shouldScopeCompanySupervisorStudentCompanies()) {
            return $this->applyCompanySupervisorStudentCompanyScope(
                StudentCompany::query()->where('student_id', $studentUserId)
            )->exists();
        }

        return true;
    }

    protected function shouldScopeCompanySupervisorStudentCompanies(): bool
    {
        return auth()->user()?->hasRole(UserRole::COMPANY_SUPERVISOR->value)
            && ! $this->currentUserIsAdmin();
    }

    protected function currentUserIsAdmin(): bool
    {
        return auth()->user()?->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ]) ?? false;
    }

    private function applyStudentCompanySupervisorExistsScope(Builder $query, int $supervisorUserId): Builder
    {
        $studentCompanyTable = $query->getModel()->getTable();
        $pivotTable = config('ppuds.table_prefix').'branch_department';

        return $query
            ->whereNotNull("{$studentCompanyTable}.branch_id")
            ->whereNotNull("{$studentCompanyTable}.department_id")
            ->whereExists(function ($subQuery) use ($studentCompanyTable, $pivotTable, $supervisorUserId) {
                $subQuery
                    ->select(DB::raw(1))
                    ->from($pivotTable)
                    ->whereColumn("{$pivotTable}.branch_id", "{$studentCompanyTable}.branch_id")
                    ->whereColumn("{$pivotTable}.company_department_id", "{$studentCompanyTable}.department_id")
                    ->where("{$pivotTable}.user_id", $supervisorUserId);
            });
    }
}

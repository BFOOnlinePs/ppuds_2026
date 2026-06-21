<?php

namespace Modules\PPUDS\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait SearchesStudentAttendanceRelationships
{
    protected function applyStudentSearchToAttendanceQuery(Builder $query, string $search): Builder
    {
        return $query->whereHas(
            'studentCompany',
            fn (Builder $studentCompanyQuery): Builder => $this->applyStudentSearchToStudentCompanyQuery($studentCompanyQuery, $search)
        );
    }

    protected function applyCompanySearchToAttendanceQuery(Builder $query, string $search): Builder
    {
        return $query->whereHas(
            'studentCompany',
            fn (Builder $studentCompanyQuery): Builder => $this->applyCompanySearchToStudentCompanyQuery($studentCompanyQuery, $search)
        );
    }

    protected function applyStudentSearchToStudentCompanyQuery(Builder $query, string $search): Builder
    {
        $userColumns = $this->searchableUserColumns();

        return $query->where(function (Builder $query) use ($search, $userColumns): void {
            $hasCondition = false;

            if ($userColumns !== []) {
                $query->whereHas('student', function (Builder $studentQuery) use ($search, $userColumns): void {
                    $studentQuery->where(function (Builder $studentQuery) use ($search, $userColumns): void {
                        foreach ($userColumns as $index => $column) {
                            $method = $index === 0 ? 'where' : 'orWhere';

                            $studentQuery->{$method}($column, 'like', "%{$search}%");
                        }
                    });
                });

                $hasCondition = true;
            }

            $method = $hasCondition ? 'orWhereHas' : 'whereHas';

            $query->{$method}(
                'student.studentProfile',
                fn (Builder $profileQuery): Builder => $profileQuery->where('student_number', 'like', "%{$search}%")
            );
        });
    }

    protected function applyCompanySearchToStudentCompanyQuery(Builder $query, string $search): Builder
    {
        return $query->whereHas(
            'company',
            fn (Builder $companyQuery): Builder => $companyQuery->whereTranslationLike('name', "%{$search}%")
        );
    }

    protected function searchableUserColumns(): array
    {
        static $columns = null;

        return $columns ??= array_values(array_filter(
            ['name', 'name_en', 'email', 'phone'],
            fn (string $column): bool => Schema::hasColumn('users', $column)
        ));
    }
}

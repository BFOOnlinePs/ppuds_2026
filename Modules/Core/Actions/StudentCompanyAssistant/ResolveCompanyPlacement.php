<?php

namespace Modules\Core\Actions\StudentCompanyAssistant;

use Illuminate\Support\Collection;
use Modules\PPUDS\Entities\Company;

class ResolveCompanyPlacement
{
    public function handle(int $companyId, ?int $branchId = null, ?int $departmentId = null): array
    {
        $company = Company::query()
            ->with('branches.departments')
            ->find($companyId);

        if (! $company) {
            return [null, null];
        }

        $branches = $company->branches instanceof Collection
            ? $company->branches
            : collect();

        $branch = $branchId ? $branches->firstWhere('id', $branchId) : null;
        $branch ??= $branches->first();

        $departments = $branch?->departments instanceof Collection
            ? $branch->departments
            : collect();

        $department = $departmentId ? $departments->firstWhere('id', $departmentId) : null;
        $department ??= $departments->first();

        return [$branch?->id, $department?->id];
    }
}

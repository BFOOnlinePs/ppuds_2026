<?php

namespace Modules\PPUDS\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Enums\TrainingStatus;

/**
 * Answers "does this person belong to this company, in this role?" — the
 * check behind company QR sign-in.
 *
 * Each role that can be tied to a company has one entry in RESOLVERS, pointing
 * at the method that knows how that role is linked. Supporting another actor
 * means adding one entry and one method here; nothing in the sign-in flow
 * names a role. Roles with no entry (administrators, university staff) are
 * never tied to a company, so they cannot use company QR sign-in.
 *
 * Every link goes through the existing relationships, and placements are
 * scoped to the current semester exactly as the company pages scope them.
 */
class CompanyMembershipService
{
    /** @var array<string, string> role name => resolver method */
    private const RESOLVERS = [
        UserRole::COMPANY_SUPERVISOR->value => 'supervisesBranchOf',
        UserRole::STUDENT->value => 'isTrainingAt',
        UserRole::EVALUATION_SUPERVISOR->value => 'evaluatesStudentsOf',
        UserRole::PRACTICAL_TRAINING_SUPERVISOR->value => 'supervisesStudentsOf',
    ];

    /**
     * Role names that can be tied to a company.
     *
     * @return array<int, string>
     */
    public static function supportedRoles(): array
    {
        return array_keys(self::RESOLVERS);
    }

    public function supports(string $role): bool
    {
        return array_key_exists($role, self::RESOLVERS);
    }

    public function belongsTo(User $user, Company $company, string $role): bool
    {
        if (! $this->supports($role) || ! $user->hasRole($role)) {
            return false;
        }

        return $this->{self::RESOLVERS[$role]}($user, $company);
    }

    /**
     * The first of the allowed roles through which the user belongs to the
     * company, or null when none of them does.
     *
     * @param  array<int, string>  $allowedRoles
     */
    public function qualifyingRole(User $user, Company $company, array $allowedRoles): ?string
    {
        foreach ($allowedRoles as $role) {
            if ($this->belongsTo($user, $company, (string) $role)) {
                return (string) $role;
            }
        }

        return null;
    }

    /** Holds a supervisor seat in one of the company's branches. */
    protected function supervisesBranchOf(User $user, Company $company): bool
    {
        return $company->branches()
            ->whereHas('supervisors', fn (Builder $query) => $query->whereKey($user->getKey()))
            ->exists();
    }

    /** Is placed at the company this semester and still training there. */
    protected function isTrainingAt(User $user, Company $company): bool
    {
        return $company->currentStudentCompanies()
            ->where('student_id', $user->getKey())
            ->where('status', TrainingStatus::AVAILABLE->value)
            ->exists();
    }

    /** Evaluates at least one of the company's placements this semester. */
    protected function evaluatesStudentsOf(User $user, Company $company): bool
    {
        return $company->currentStudentCompanies()
            ->where('evaluation_supervisor_id', $user->getKey())
            ->where('status', '!=', TrainingStatus::DELETED->value)
            ->exists();
    }

    /** Is the university supervisor of a student placed at the company this semester. */
    protected function supervisesStudentsOf(User $user, Company $company): bool
    {
        return $company->currentStudentCompanies()
            ->where('status', '!=', TrainingStatus::DELETED->value)
            ->whereHas('registration', fn (Builder $query) => $query->where('supervisor_id', $user->getKey()))
            ->exists();
    }
}

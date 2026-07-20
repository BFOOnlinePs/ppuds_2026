<?php

namespace Modules\PPUDS\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\TrainingStatus;

class ChatContactService
{
    public function contactsFor(User $user, ?string $search = null): Collection
    {
        $contactTypes = $this->contactTypesFor($user);

        if ($contactTypes === []) {
            return collect();
        }

        return User::query()
            ->whereIn('id', array_keys($contactTypes))
            ->when($search, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (User $contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'image' => $contact->getProfileImageUrlAttribute(),
                'contact_types' => $contactTypes[$contact->id] ?? [],
                'supervisor_types' => $contactTypes[$contact->id] ?? [],
            ])
            ->values();
    }

    public function optionListFor(User $user): array
    {
        return $this->contactsFor($user)
            ->pluck('name', 'id')
            ->toArray();
    }

    public function canStartConversation(User $user, User $receiver): bool
    {
        if ($user->is($receiver)) {
            return false;
        }

        if ($this->canChatWithAnyUser($user)) {
            return true;
        }

        return array_key_exists($receiver->id, $this->contactTypesFor($user));
    }

    public function contactTypesFor(User $user): array
    {
        if ($this->canChatWithAnyUser($user)) {
            return User::query()
                ->whereKeyNot($user->id)
                ->pluck('id')
                ->mapWithKeys(fn (int $id): array => [$id => ['user']])
                ->toArray();
        }

        $contactTypes = [];

        if ($user->hasRole(UserRole::STUDENT->value)) {
            $this->addStudentContacts($user, $contactTypes);
        } elseif ($user->hasRole(UserRole::PRACTICAL_TRAINING_SUPERVISOR->value)) {
            $this->addUniversitySupervisorContacts($user, $contactTypes);
        } elseif ($user->hasRole(UserRole::COMPANY_SUPERVISOR->value)) {
            $this->addCompanySupervisorContacts($user, $contactTypes);
        }

        unset($contactTypes[$user->id]);

        return collect($contactTypes)
            ->map(fn (array $types): array => array_values(array_unique(array_filter($types))))
            ->filter()
            ->toArray();
    }

    private function addStudentContacts(User $student, array &$contactTypes): void
    {
        $registrations = $this->currentRegistrationsQuery()
            ->where('student_id', $student->id)
            ->get(['id', 'supervisor_id']);

        $registrations
            ->pluck('supervisor_id')
            ->each(fn ($supervisorId) => $this->addContactType($contactTypes, $supervisorId, 'university_supervisor'));

        $studentCompanies = StudentCompany::query()
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->get(['branch_id', 'department_id']);

        $this->companySupervisorIdsForStudentCompanies($studentCompanies)
            ->each(fn ($supervisorId) => $this->addContactType($contactTypes, $supervisorId, 'company_supervisor'));
    }

    private function addUniversitySupervisorContacts(User $supervisor, array &$contactTypes): void
    {
        $registrations = $this->currentRegistrationsQuery()
            ->where('supervisor_id', $supervisor->id)
            ->get(['id', 'student_id']);

        $registrations
            ->pluck('student_id')
            ->each(fn ($studentId) => $this->addContactType($contactTypes, $studentId, 'student'));

        $studentCompanies = StudentCompany::query()
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->get(['branch_id', 'department_id']);

        $this->companySupervisorIdsForStudentCompanies($studentCompanies)
            ->each(fn ($companySupervisorId) => $this->addContactType($contactTypes, $companySupervisorId, 'company_supervisor'));
    }

    private function addCompanySupervisorContacts(User $supervisor, array &$contactTypes): void
    {
        $assignments = DB::table($this->branchDepartmentTable())
            ->where('user_id', $supervisor->id)
            ->get(['branch_id', 'company_department_id']);

        if ($assignments->isEmpty()) {
            return;
        }

        $studentCompanies = StudentCompany::query()
            ->where('status', TrainingStatus::AVAILABLE)
            ->where(function (Builder $query) use ($assignments): void {
                $assignments->each(function ($assignment) use ($query): void {
                    $query->orWhere(function (Builder $query) use ($assignment): void {
                        $query->where('branch_id', $assignment->branch_id)
                            ->where('department_id', $assignment->company_department_id);
                    });
                });
            })
            ->with('registration:id,student_id,supervisor_id')
            ->get(['id', 'registration_id', 'student_id', 'branch_id', 'department_id']);

        $studentCompanies
            ->pluck('student_id')
            ->each(fn ($studentId) => $this->addContactType($contactTypes, $studentId, 'student'));

        $studentCompanies
            ->pluck('registration.supervisor_id')
            ->each(fn ($universitySupervisorId) => $this->addContactType($contactTypes, $universitySupervisorId, 'university_supervisor'));
    }

    private function companySupervisorIdsForStudentCompanies(Collection $studentCompanies): Collection
    {
        $branchDepartmentPairs = $studentCompanies
            ->filter(fn (StudentCompany $studentCompany): bool => (bool) $studentCompany->branch_id && (bool) $studentCompany->department_id)
            ->map(fn (StudentCompany $studentCompany): array => [
                'branch_id' => $studentCompany->branch_id,
                'department_id' => $studentCompany->department_id,
            ])
            ->unique(fn (array $pair): string => $pair['branch_id'].'-'.$pair['department_id'])
            ->values();

        $branchIds = $studentCompanies
            ->filter(fn (StudentCompany $studentCompany): bool => (bool) $studentCompany->branch_id && ! $studentCompany->department_id)
            ->pluck('branch_id')
            ->unique()
            ->values();

        $supervisorIds = collect();

        if ($branchDepartmentPairs->isNotEmpty()) {
            $supervisorIds = $supervisorIds->merge(
                DB::table($this->branchDepartmentTable())
                    ->where(function ($query) use ($branchDepartmentPairs): void {
                        $branchDepartmentPairs->each(function (array $pair) use ($query): void {
                            $query->orWhere(function ($query) use ($pair): void {
                                $query->where('branch_id', $pair['branch_id'])
                                    ->where('company_department_id', $pair['department_id']);
                            });
                        });
                    })
                    ->pluck('user_id')
            );
        }

        if ($branchIds->isNotEmpty()) {
            $supervisorIds = $supervisorIds->merge(
                DB::table($this->branchDepartmentTable())
                    ->whereIn('branch_id', $branchIds)
                    ->pluck('user_id')
            );
        }

        return $supervisorIds
            ->filter()
            ->unique()
            ->values();
    }

    private function currentRegistrationsQuery(): Builder
    {
        return Registration::query()
            ->whereHas('studentCompany', fn (Builder $query) => $query->where('status', TrainingStatus::AVAILABLE));
    }

    private function addContactType(array &$contactTypes, mixed $userId, string $type): void
    {
        if (! $userId) {
            return;
        }

        $contactTypes[(int) $userId][] = $type;
    }

    private function canChatWithAnyUser(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ]);
    }

    private function branchDepartmentTable(): string
    {
        return config('ppuds.table_prefix').'branch_department';
    }
}

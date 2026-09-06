<?php

namespace Modules\PPUDS\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Enums\UserRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        $allPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        $this->syncRolePermissions(UserRole::SUPER_ADMIN->value, $allPermissions);
        $this->syncRolePermissions(UserRole::ADMIN->value, $allPermissions);

        foreach ($this->rolePermissions() as $role => $permissions) {
            $this->syncRolePermissions($role, $permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function syncRolePermissions(string $roleName, array $permissions): void
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        $existingPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', array_values(array_unique($permissions)))
            ->pluck('name')
            ->all();

        $role->syncPermissions($existingPermissions);

        $this->command?->info("Role {$roleName} synced with " . count($existingPermissions) . ' permissions');
    }

    private function rolePermissions(): array
    {
        return [
            UserRole::STUDENT->value => [
                ...$this->read('StudentCompany'),
                'StudentCompany Details',
                ...$this->read('LeaveRequest'),
                'LeaveRequest Create',
                ...$this->read('StudentAttendance'),
                'StudentAttendance Create',
                'StudentAttendance Report List',
                'Attendance Map View',
                'StudentReport View',
                'StudentReport Info',
                ...$this->read('Survey'),
                'Survey Submit',
                ...$this->read('Announcement'),
                'Announcement Details',
                ...$this->read('Alumni Announcement'),
                ...$this->manage('Note'),
                'WorkExperience View List',
                'WorkExperience Create',
                'WorkExperience Update',
                'WorkExperience Delete',
                ...$this->branchRead(),
            ],

            UserRole::HEAD_OF_DEPARTMENT->value => [
                'Dashboard Statistics Verification View',
                ...$this->read('Student'),
                'Student Details List',
                'Student Export Companies',
                ...$this->read('Supervisor'),
                'Supervisor Details List',
                ...$this->read('Company'),
                'Company Details List',
                ...$this->read('Company Category'),
                ...$this->read('Company Department'),
                ...$this->read('Major'),
                ...$this->read('Course'),
                ...$this->read('Registration'),
                ...$this->read('StudentCompany'),
                'StudentCompany Details',
                ...$this->read('FieldVisit'),
                ...$this->read('LeaveRequest'),
                'LeaveRequest UniversityApprove',
                ...$this->read('StudentAttendance'),
                'StudentAttendance Report List',
                'Attendance Map View',
                'StudentReport View',
                'StudentReport Info',
                ...$this->read('Survey'),
                ...$this->read('Announcement'),
                'Announcement Details',
                ...$this->read('Report'),
                'Supervisor Report View List',
                'Report AcademicFeedback',
                ...$this->read('Alumni Announcement'),
                'Alumni Report View List',
                ...$this->branchRead(),
            ],

            UserRole::ADMINISTRATIVE_ASSISTANT->value => [
                'Dashboard Statistics Verification View',
                ...$this->read('Student'),
                'Student Details List',
                'Student Export Companies',
                ...$this->manage('Company Category'),
                ...$this->manage('Company Department'),
                ...$this->read('Company'),
                'Company Details List',
                ...$this->read('Major'),
                ...$this->read('Course'),
                ...$this->manage('Registration'),
                'Registration Select Supervisor',
                ...$this->manage('StudentCompany'),
                'StudentCompany Details',
                ...$this->manage('LeaveRequest'),
                ...$this->read('StudentAttendance'),
                'StudentAttendance Report List',
                'Attendance Map View',
                'StudentReport View',
                'StudentReport Info',
                ...$this->read('Announcement'),
                'Announcement Details',
                ...$this->read('Survey'),
                ...$this->manage('Note'),
                ...$this->read('Report'),
                'Supervisor Report View List',
                ...$this->read('Alumni Announcement'),
                'Alumni Report View List',
                ...$this->branchRead(),
            ],

            UserRole::M_AND_E_OFFICER->value => [
                'Dashboard Statistics Verification View',
                ...$this->read('Student'),
                'Student Details List',
                'Student Export Companies',
                ...$this->read('Company'),
                'Company Details List',
                ...$this->read('Registration'),
                ...$this->read('StudentCompany'),
                'StudentCompany Details',
                ...$this->read('FieldVisit'),
                ...$this->read('LeaveRequest'),
                ...$this->read('StudentAttendance'),
                'StudentAttendance Report List',
                'Attendance Map View',
                'StudentReport View',
                'StudentReport Info',
                ...$this->manage('Survey'),
                ...$this->read('Announcement'),
                'Announcement Details',
                ...$this->read('Report'),
                'Supervisor Report View List',
                ...$this->read('Alumni Announcement'),
                'Alumni Report View List',
                ...$this->branchRead(),
            ],

            UserRole::CORPORATE_RELATIONS_OFFICER->value => [
                'Dashboard Statistics Verification View',
                ...$this->read('Student'),
                'Student Details List',
                'Student Export Companies',
                ...$this->manage('Company'),
                'Company Details List',
                ...$this->manage('Company Category'),
                ...$this->manage('Company Department'),
                ...$this->read('Major'),
                ...$this->read('Course'),
                ...$this->read('Registration'),
                ...$this->manage('StudentCompany'),
                'StudentCompany Details',
                ...$this->read('FieldVisit'),
                ...$this->read('LeaveRequest'),
                ...$this->read('StudentAttendance'),
                'StudentAttendance Report List',
                'Attendance Map View',
                ...$this->read('Announcement'),
                'Announcement Details',
                ...$this->read('Survey'),
                ...$this->read('Report'),
                'Supervisor Report View List',
                ...$this->read('Alumni Announcement'),
                'Alumni Report View List',
                ...$this->branchRead(),
            ],

            UserRole::PRACTICAL_TRAINING_SUPERVISOR->value => [
                'Dashboard Statistics Verification View',
                ...$this->read('Student'),
                'Student Details List',
                'Student Export Companies',
                ...$this->read('PracticalSupervisorStudent'),
                ...$this->read('Supervisor'),
                'Supervisor Details List',
                ...$this->read('Company'),
                'Company Details List',
                ...$this->read('Registration'),
                ...$this->read('StudentCompany'),
                'StudentCompany Details',
                'StudentCompany Assign Evaluation Supervisor',
                ...$this->manage('FieldVisit'),
                ...$this->read('LeaveRequest'),
                'LeaveRequest UniversityApprove',
                ...$this->read('StudentAttendance'),
                'StudentAttendance Update',
                'StudentAttendance Report List',
                'Attendance Map View',
                'StudentReport View',
                'StudentReport Info',
                ...$this->read('Survey'),
                ...$this->read('Announcement'),
                'Announcement Details',
                ...$this->manage('Note'),
                ...$this->read('Report'),
                'Report AcademicFeedback',
                ...$this->read('Alumni Announcement'),
                'Alumni Report View List',
                ...$this->branchRead(),
            ],

            UserRole::COMPANY_SUPERVISOR->value => [
                'Company View',
                'Company Info',
                ...$this->read('Student'),
                'Student Details List',
                'Student Export Companies',
                ...$this->read('StudentCompany'),
                'StudentCompany Details',
                ...$this->read('LeaveRequest'),
                'LeaveRequest CompanyApprove',
                ...$this->read('StudentAttendance'),
                'StudentAttendance Report List',
                'Attendance Map View',
                'StudentReport View',
                'StudentReport Info',
                'Report View List',
                'Report CompanyFeedback',
                ...$this->read('Survey'),
                'Survey Submit',
                ...$this->read('Announcement'),
                'Announcement Details',
                ...$this->manage('Note'),
                ...$this->read('Alumni Announcement'),
                'Alumni Report View List',
                ...$this->branchRead(),
            ],

            UserRole::EVALUATION_SUPERVISOR->value => [
                'EvaluationSupervisorStudent View List',
                'EvaluationSupervisorStudent Grade',
            ],

            UserRole::USER->value => [],
            UserRole::CUSTOMER->value => [],
        ];
    }

    private function read(string $resource): array
    {
        return [
            "{$resource} View List",
            "{$resource} View",
            "{$resource} Info",
        ];
    }

    private function manage(string $resource): array
    {
        return [
            ...$this->read($resource),
            "{$resource} Create",
            "{$resource} Update",
            "{$resource} Delete",
        ];
    }

    private function branchRead(): array
    {
        return [
            'Branch View List',
            'Branch View',
            'Branch Info',
            'Branch View Details',
        ];
    }
}

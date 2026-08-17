<?php

namespace Modules\PPUDS\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Enums\UserRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        // Role::query()->delete();
        // Permission::query()->delete();

        $permissions = [
            // TODO Dashboard
            [
                'name' => 'Dashboard Statistics Verification View',
                'guard_name' => 'web',
                'module_name' => 'Dashboard Statistics Verification',
            ],

            // TODO Students
            [
                'name' => 'Student View List',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],
            [
                'name' => 'Student Create',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],
            [
                'name' => 'Student Update',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],
            [
                'name' => 'Student Delete',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],
            [
                'name' => 'Student View',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],
            [
                'name' => 'Student Info',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],
            [
                'name' => 'Student Details List',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],
            [
                'name' => 'Student Export Companies',
                'guard_name' => 'web',
                'module_name' => 'Student',
            ],

            [
                'name' => 'Supervisor View List',
                'guard_name' => 'web',
                'module_name' => 'Supervisor',
            ],
            [
                'name' => 'Supervisor Create',
                'guard_name' => 'web',
                'module_name' => 'Supervisor',
            ],
            [
                'name' => 'Supervisor View',
                'guard_name' => 'web',
                'module_name' => 'Supervisor',
            ],
            [
                'name' => 'Supervisor Details List',
                'guard_name' => 'web',
                'module_name' => 'Supervisor',
            ],

            [
                'name' => 'PracticalSupervisorStudent View List',
                'guard_name' => 'web',
                'module_name' => 'Practical Supervisor Student',
            ],
            [
                'name' => 'PracticalSupervisorStudent View',
                'guard_name' => 'web',
                'module_name' => 'Practical Supervisor Student',
            ],
            [
                'name' => 'PracticalSupervisorStudent Info',
                'guard_name' => 'web',
                'module_name' => 'Practical Supervisor Student',
            ],

            [
                'name' => 'WorkExperience View List',
                'guard_name' => 'web',
                'module_name' => 'WorkExperience',
            ],
            [
                'name' => 'WorkExperience Create',
                'guard_name' => 'web',
                'module_name' => 'WorkExperience',
            ],
            [
                'name' => 'WorkExperience Update',
                'guard_name' => 'web',
                'module_name' => 'WorkExperience',
            ],
            [
                'name' => 'WorkExperience Delete',
                'guard_name' => 'web',
                'module_name' => 'WorkExperience',
            ],


            // TODO Company Category
            [
                'name' => 'Company Category View List',
                'guard_name' => 'web',
                'module_name' => 'Company Category',
            ],
            [
                'name' => 'Company Category Create',
                'guard_name' => 'web',
                'module_name' => 'Company Category',
            ],
            [
                'name' => 'Company Category Update',
                'guard_name' => 'web',
                'module_name' => 'Company Category',
            ],
            [
                'name' => 'Company Category Delete',
                'guard_name' => 'web',
                'module_name' => 'Company Category',
            ],
            [
                'name' => 'Company Category View',
                'guard_name' => 'web',
                'module_name' => 'Company Category',
            ],
            [
                'name' => 'Company Category Info',
                'guard_name' => 'web',
                'module_name' => 'Company Category',
            ],


            // TODO Companies
            [
                'name' => 'Company View List',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Company Create',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Company Update',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Company Delete',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Company View',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Company Info',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Company Details List',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],


            // TODO Companies Department
            [
                'name' => 'Company Department View List',
                'guard_name' => 'web',
                'module_name' => 'Company Department',
            ],
            [
                'name' => 'Company Department Create',
                'guard_name' => 'web',
                'module_name' => 'Company Department',
            ],
            [
                'name' => 'Company Department Update',
                'guard_name' => 'web',
                'module_name' => 'Company Department',
            ],
            [
                'name' => 'Company Department Delete',
                'guard_name' => 'web',
                'module_name' => 'Company Department',
            ],
            [
                'name' => 'Company Department View',
                'guard_name' => 'web',
                'module_name' => 'Company Department',
            ],
            [
                'name' => 'Company Department Info',
                'guard_name' => 'web',
                'module_name' => 'Company Department',
            ],


            // TODO Major
            [
                'name' => 'Major View List',
                'guard_name' => 'web',
                'module_name' => 'Major',
            ],
            [
                'name' => 'Major Create',
                'guard_name' => 'web',
                'module_name' => 'Major',
            ],
            [
                'name' => 'Major Update',
                'guard_name' => 'web',
                'module_name' => 'Major',
            ],
            [
                'name' => 'Major Delete',
                'guard_name' => 'web',
                'module_name' => 'Company',
            ],
            [
                'name' => 'Major View',
                'guard_name' => 'web',
                'module_name' => 'Major',
            ],
            [
                'name' => 'Major Info',
                'guard_name' => 'web',
                'module_name' => 'Major',
            ],


            // TODO Course
            [
                'name' => 'Course View List',
                'guard_name' => 'web',
                'module_name' => 'Course',
            ],
            [
                'name' => 'Course Create',
                'guard_name' => 'web',
                'module_name' => 'Course',
            ],
            [
                'name' => 'Course Update',
                'guard_name' => 'web',
                'module_name' => 'Course',
            ],
            [
                'name' => 'Course Delete',
                'guard_name' => 'web',
                'module_name' => 'Course',
            ],
            [
                'name' => 'Course View',
                'guard_name' => 'web',
                'module_name' => 'Course',
            ],
            [
                'name' => 'Course Info',
                'guard_name' => 'web',
                'module_name' => 'Course',
            ],


            // TODO Registration
            [
                'name' => 'Registration View List',
                'guard_name' => 'web',
                'module_name' => 'Registration',
            ],
            [
                'name' => 'Registration Create',
                'guard_name' => 'web',
                'module_name' => 'Registration',
            ],
            [
                'name' => 'Registration Update',
                'guard_name' => 'web',
                'module_name' => 'Registration',
            ],
            [
                'name' => 'Registration Select Supervisor',
                'guard_name' => 'web',
                'module_name' => 'Registration',
            ],
            [
                'name' => 'Registration Delete',
                'guard_name' => 'web',
                'module_name' => 'Registration',
            ],
            [
                'name' => 'Registration View',
                'guard_name' => 'web',
                'module_name' => 'Registration',
            ],
            [
                'name' => 'Registration Info',
                'guard_name' => 'web',
                'module_name' => 'Registration',
            ],


            // TODO StudentCompany
            [
                'name' => 'StudentCompany View List',
                'guard_name' => 'web',
                'module_name' => 'StudentCompany',
            ],
            [
                'name' => 'StudentCompany Create',
                'guard_name' => 'web',
                'module_name' => 'StudentCompany',
            ],
            [
                'name' => 'StudentCompany Update',
                'guard_name' => 'web',
                'module_name' => 'StudentCompany',
            ],
            [
                'name' => 'StudentCompany Delete',
                'guard_name' => 'web',
                'module_name' => 'StudentCompany',
            ],
            [
                'name' => 'StudentCompany View',
                'guard_name' => 'web',
                'module_name' => 'StudentCompany',
            ],
            [
                'name' => 'StudentCompany Info',
                'guard_name' => 'web',
                'module_name' => 'StudentCompany',
            ],
            [
                'name' => 'StudentCompany Details',
                'guard_name' => 'web',
                'module_name' => 'StudentCompany',
            ],

            // TODO FieldVisit StudentCompany Details
            [
                'name' => 'FieldVisit View List',
                'guard_name' => 'web',
                'module_name' => 'Field Visit',
            ],
            [
                'name' => 'FieldVisit Create',
                'guard_name' => 'web',
                'module_name' => 'Field Visit',
            ],
            [
                'name' => 'FieldVisit Update',
                'guard_name' => 'web',
                'module_name' => 'Field Visit',
            ],
            [
                'name' => 'FieldVisit Delete',
                'guard_name' => 'web',
                'module_name' => 'Field Visit',
            ],
            [
                'name' => 'FieldVisit View',
                'guard_name' => 'web',
                'module_name' => 'Field Visit',
            ],
            [
                'name' => 'FieldVisit Info',
                'guard_name' => 'web',
                'module_name' => 'Field Visit',
            ],


            // TODO Announcement
            [
                'name' => 'Announcement View List',
                'guard_name' => 'web',
                'module_name' => 'Announcement',
            ],
            [
                'name' => 'Announcement Create',
                'guard_name' => 'web',
                'module_name' => 'Announcement',
            ],
            [
                'name' => 'Announcement Update',
                'guard_name' => 'web',
                'module_name' => 'Announcement',
            ],
            [
                'name' => 'Announcement Delete',
                'guard_name' => 'web',
                'module_name' => 'Announcement',
            ],
            [
                'name' => 'Announcement View',
                'guard_name' => 'web',
                'module_name' => 'Announcement',
            ],
            [
                'name' => 'Announcement Info',
                'guard_name' => 'web',
                'module_name' => 'Announcement',
            ],
            [
                'name' => 'Announcement Details',
                'guard_name' => 'web',
                'module_name' => 'Announcement',
            ],

            // TODO Announcement Category
            [
                'name' => 'Announcement Category View List',
                'guard_name' => 'web',
                'module_name' => 'Announcement Category',
            ],
            [
                'name' => 'Announcement Category Create',
                'guard_name' => 'web',
                'module_name' => 'Announcement Category',
            ],
            [
                'name' => 'Announcement Category Update',
                'guard_name' => 'web',
                'module_name' => 'Announcement Category',
            ],
            [
                'name' => 'Announcement Category Delete',
                'guard_name' => 'web',
                'module_name' => 'Announcement Category',
            ],
            [
                'name' => 'Announcement Category View',
                'guard_name' => 'web',
                'module_name' => 'Announcement Category',
            ],
            [
                'name' => 'Announcement Category Info',
                'guard_name' => 'web',
                'module_name' => 'Announcement Category',
            ],

            // TODO Alumni Report
            [
                'name' => 'Alumni Report View List',
                'guard_name' => 'web',
                'module_name' => 'Alumni Report',
            ],

            // TODO Alumni Announcement
            [
                'name' => 'Alumni Announcement View List',
                'guard_name' => 'web',
                'module_name' => 'Alumni Announcement',
            ],
            [
                'name' => 'Alumni Announcement Create',
                'guard_name' => 'web',
                'module_name' => 'Alumni Announcement',
            ],
            [
                'name' => 'Alumni Announcement Update',
                'guard_name' => 'web',
                'module_name' => 'Alumni Announcement',
            ],
            [
                'name' => 'Alumni Announcement Delete',
                'guard_name' => 'web',
                'module_name' => 'Alumni Announcement',
            ],
            [
                'name' => 'Alumni Announcement View',
                'guard_name' => 'web',
                'module_name' => 'Alumni Announcement',
            ],
            [
                'name' => 'Alumni Announcement Info',
                'guard_name' => 'web',
                'module_name' => 'Alumni Announcement',
            ],


            // TODO LeaveRequest
            [
                'name' => 'LeaveRequest View List',
                'guard_name' => 'web',
                'module_name' => 'Leave Request',
            ],
            [
                'name' => 'LeaveRequest Create',
                'guard_name' => 'web',
                'module_name' => 'Leave Request',
            ],
            [
                'name' => 'LeaveRequest Update',
                'guard_name' => 'web',
                'module_name' => 'Leave Request',
            ],
            [
                'name' => 'LeaveRequest Delete',
                'guard_name' => 'web',
                'module_name' => 'Leave Request',
            ],
            [
                'name' => 'LeaveRequest View',
                'guard_name' => 'web',
                'module_name' => 'Leave Request',
            ],
            [
                'name' => 'LeaveRequest Info',
                'guard_name' => 'web',
                'module_name' => 'Leave Request',
            ],
            [
                'name' => 'LeaveRequest CompanyApprove',
                'guard_name' => 'web',
                'module_name' => 'Leave Request',
            ],
            [
                'name' => 'LeaveRequest UniversityApprove',
                'guard_name' => 'web',
                'module_name' => 'Leave Request',
            ],


            // TODO StudentAttendance
            [
                'name' => 'StudentAttendance View List',
                'guard_name' => 'web',
                'module_name' => 'Student Attendance',
            ],
            [
                'name' => 'StudentAttendance Create',
                'guard_name' => 'web',
                'module_name' => 'Student Attendance',
            ],
            [
                'name' => 'StudentAttendance Update',
                'guard_name' => 'web',
                'module_name' => 'Student Attendance',
            ],
            [
                'name' => 'StudentAttendance Delete',
                'guard_name' => 'web',
                'module_name' => 'Student Attendance',
            ],
            [
                'name' => 'StudentAttendance View',
                'guard_name' => 'web',
                'module_name' => 'Student Attendance',
            ],
            [
                'name' => 'StudentAttendance Info',
                'guard_name' => 'web',
                'module_name' => 'Student Attendance',
            ],
            [
                'name' => 'StudentAttendance Report List',
                'guard_name' => 'web',
                'module_name' => 'Student Attendance',
            ],
            [
                'name' => 'Attendance Map View',
                'guard_name' => 'web',
                'module_name' => 'Student Attendance',
            ],

            // TODO StudentReport
            [
                'name' => 'StudentReport View',
                'guard_name' => 'web',
                'module_name' => 'Student Report',
            ],
            [
                'name' => 'StudentReport Info',
                'guard_name' => 'web',
                'module_name' => 'Student Report',
            ],
            [
                'name' => 'StudentReport Delete',
                'guard_name' => 'web',
                'module_name' => 'Student Report',
            ],



            // TODO Survey
            [
                'name' => 'Survey View List',
                'guard_name' => 'web',
                'module_name' => 'Survey',
            ],
            [
                'name' => 'Survey Create',
                'guard_name' => 'web',
                'module_name' => 'Survey',
            ],
            [
                'name' => 'Survey Update',
                'guard_name' => 'web',
                'module_name' => 'Survey',
            ],
            [
                'name' => 'Survey Delete',
                'guard_name' => 'web',
                'module_name' => 'Survey',
            ],
            [
                'name' => 'Survey View',
                'guard_name' => 'web',
                'module_name' => 'Survey',
            ],
            [
                'name' => 'Survey Info',
                'guard_name' => 'web',
                'module_name' => 'Survey',
            ],
            [
                'name' => 'Survey Submit',
                'guard_name' => 'web',
                'module_name' => 'Survey',
            ],


            // TODO Note
            [
                'name' => 'Note View List',
                'guard_name' => 'web',
                'module_name' => 'Note',
            ],
            [
                'name' => 'Note Create',
                'guard_name' => 'web',
                'module_name' => 'Note',
            ],
            [
                'name' => 'Note Update',
                'guard_name' => 'web',
                'module_name' => 'Note',
            ],
            [
                'name' => 'Note Delete',
                'guard_name' => 'web',
                'module_name' => 'Note',
            ],
            [
                'name' => 'Note View',
                'guard_name' => 'web',
                'module_name' => 'Note',
            ],
            [
                'name' => 'Note Info',
                'guard_name' => 'web',
                'module_name' => 'Note',
            ],


            // TODO Report
            [
                'name' => 'Report View List',
                'guard_name' => 'web',
                'module_name' => 'Report',
            ],
            [
                'name' => 'Report Create',
                'guard_name' => 'web',
                'module_name' => 'Report',
            ],
            [
                'name' => 'Report Update',
                'guard_name' => 'web',
                'module_name' => 'Report',
            ],
            [
                'name' => 'Report Delete',
                'guard_name' => 'web',
                'module_name' => 'Report',
            ],
            [
                'name' => 'Report View',
                'guard_name' => 'web',
                'module_name' => 'Report',
            ],
            [
                'name' => 'Report Info',
                'guard_name' => 'web',
                'module_name' => 'Report',
            ],
            [
                'name' => 'Report CompanyFeedback',
                'guard_name' => 'web',
                'module_name' => 'Report',
            ],
            [
                'name' => 'Report AcademicFeedback',
                'guard_name' => 'web',
                'module_name' => 'Report',
            ],

            // TODO Sync System Data
            [
                'name' => 'Sync System Data View',
                'guard_name' => 'web',
                'module_name' => 'Sync System Data',
            ],
            [
                'name' => 'Sync System Data Sync',
                'guard_name' => 'web',
                'module_name' => 'Sync System Data',
            ],

            // TODO Sync Company Supervisors
            [
                'name' => 'Sync Company Supervisors View',
                'guard_name' => 'web',
                'module_name' => 'Sync Company Supervisors',
            ],
            [
                'name' => 'Sync Company Supervisors Sync',
                'guard_name' => 'web',
                'module_name' => 'Sync Company Supervisors',
            ],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['module_name' => $perm['module_name']]
            );
            $this->command->info("Permission created: {$perm['name']}");

            foreach ([UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value] as $roleName) {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
                $role->givePermissionTo($perm['name']);
                $this->command?->info("Permission {$perm['name']} assigned to role {$roleName}");
            }
        }
    }
}

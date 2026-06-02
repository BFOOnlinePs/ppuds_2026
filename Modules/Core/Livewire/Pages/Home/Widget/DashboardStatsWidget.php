<?php

namespace Modules\Core\Livewire\Pages\Home\Widget;

use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Enums\StudentGender;
use Modules\PPUDS\Settings\GeneralSettings;

class DashboardStatsWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            ...$this->adminStats(),
            ...$this->studentStats(),
        ];
    }

    private function adminStats(): array
    {
        if (! $this->canViewAdminStats()) {
            return [];
        }

        $currentRegistrations = $this->currentRegistrationsQuery();
        $studentsInCompanies = $this->currentStudentCompaniesQuery()
            ->whereNotNull('company_id');
        $studentsNeedingCompany = $this->studentsNeedingCompanyQuery();
        $admittedStudentsNotMatched = $this->admittedStudentsNotMatchedQuery();
        $todayAttendance = $this->todayAttendanceStudentsQuery();

        return [
            $this->linkStat(
                Stat::make(__('Current Semester Students'), $this->countDistinct($currentRegistrations, 'student_id'))
                    ->description($this->genderDescription($currentRegistrations, 'student.studentProfile', 'student_id'))
                    ->icon('heroicon-o-academic-cap')
                    ->color('primary'),
                [
                    ['route' => 'registrations.index', 'permission' => 'Registration View List'],
                    ['route' => 'students.index', 'permission' => 'Student View List'],
                ],
            ),

            $this->linkStat(
                Stat::make(__('Students In Companies'), $this->countDistinct($studentsInCompanies, 'student_id'))
                    ->description($this->genderDescription($studentsInCompanies, 'student.studentProfile', 'student_id'))
                    ->icon('heroicon-o-building-office-2')
                    ->color('success'),
                [
                    ['route' => 'student-companies.index', 'permission' => 'StudentCompany View List'],
                ],
            ),

            $this->linkStat(
                Stat::make(__('Students Needing Company Registration'), $this->countDistinct($studentsNeedingCompany, 'student_id'))
                    ->description($this->genderDescription($studentsNeedingCompany, 'student.studentProfile', 'student_id'))
                    ->icon('heroicon-o-user-plus')
                    ->color('warning'),
                [
                    ['route' => 'student-companies.add', 'permission' => 'StudentCompany Create'],
                    ['route' => 'registrations.index', 'permission' => 'Registration View List', 'parameters' => ['without_company' => 1]],
                ],
            ),

            $this->linkStat(
                Stat::make(__('Admitted Students Not Matched'), (clone $admittedStudentsNotMatched)->count())
                    ->description(__('Students not assigned to any company'))
                    ->icon('heroicon-o-user-plus')
                    ->color('danger'),
                [
                    ['route' => 'students.index', 'permission' => 'Student View List', 'parameters' => ['not_matched' => 1]],
                ],
            ),

            $this->linkStat(
                Stat::make(__('Today Attendance'), $this->countDistinct($todayAttendance, 'student_id'))
                    ->description($this->genderDescription($todayAttendance, 'student.studentProfile', 'student_id'))
                    ->icon('heroicon-o-calendar-days')
                    ->color('info'),
                [
                    ['route' => 'student-attendances.index', 'permission' => 'StudentAttendance View List'],
                ],
            ),
        ];
    }

    private function studentStats(): array
    {
        if (! auth()->user()?->hasRole(UserRole::STUDENT->value)) {
            return [];
        }

        $hours = $this->studentWorkingHours();

        return [
            $this->linkStat(
                Stat::make(__('Attendance Hours'), $this->formatHours($hours['actual']))
                    ->description(__('Worked Hours Of Total Hours', [
                        'worked' => $this->formatHours($hours['actual']),
                        'total' => $this->formatHours($hours['total']),
                    ]))
                    ->icon('heroicon-o-clock')
                    ->color('primary'),
                [
                    ['route' => 'student-attendances.index', 'permission' => 'StudentAttendance View List'],
                ],
            ),

            $this->linkStat(
                Stat::make(__('Attendance Days'), $this->studentAttendanceDays())
                    ->description(__('Total Attendance Days'))
                    ->icon('heroicon-o-calendar-days')
                    ->color('success'),
                [
                    ['route' => 'student-attendances.index', 'permission' => 'StudentAttendance View List'],
                ],
            ),

            $this->linkStat(
                Stat::make(__('Attendance And Departure Permissions'), $this->studentLeaveRequestsCount())
                    ->description(__('Total Attendance And Departure Permissions'))
                    ->icon('heroicon-o-document-text')
                    ->color('warning'),
                [
                    ['route' => 'leave-requests.index', 'permission' => 'LeaveRequest View List'],
                ],
            ),
        ];
    }

    private function linkStat(Stat $stat, array $targets): Stat
    {
        $url = $this->firstAllowedUrl($targets);

        if (! $url) {
            return $stat;
        }

        return $stat
            ->url($url)
            ->extraAttributes([
                'class' => 'transition hover:-translate-y-0.5 hover:ring-primary-500/30 focus:outline-none focus:ring-2 focus:ring-primary-500',
            ]);
    }

    private function firstAllowedUrl(array $targets): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        foreach ($targets as $target) {
            $route = $target['route'] ?? null;
            $permission = $target['permission'] ?? null;

            if (! $route || ! Route::has($route)) {
                continue;
            }

            if ($permission && ! $user->can($permission)) {
                continue;
            }

            return route($route, $target['parameters'] ?? []);
        }

        return null;
    }

    private function currentRegistrationsQuery(): Builder
    {
        return Registration::query()
            ->where(fn (Builder $query) => $this->applyCurrentSemester($query));
    }

    private function currentStudentCompaniesQuery(): Builder
    {
        return StudentCompany::query()
            ->whereHas('registration', fn (Builder $query) => $this->applyCurrentSemester($query));
    }

    private function studentsNeedingCompanyQuery(): Builder
    {
        return $this->currentRegistrationsQuery()
            ->whereNotIn(
                'id',
                StudentCompany::query()
                    ->whereNotNull('company_id')
                    ->select('registration_id')
            );
    }

    private function admittedStudentsNotMatchedQuery(): Builder
    {
        return StudentProfile::query()
            ->whereHas('user')
            ->whereDoesntHave(
                'user.studentCompanies',
                fn (Builder $query) => $query->whereNotNull('company_id')
            )
            ->when(
                $this->shouldScopeToSupervisor(),
                fn (Builder $query) => $query->whereIn(
                    'user_id',
                    $this->currentRegistrationsQuery()->select('student_id')
                )
            );
    }

    private function todayAttendanceStudentsQuery(): Builder
    {
        return $this->currentStudentCompaniesQuery()
            ->whereHas('attendances', fn (Builder $query) => $query
                ->whereDate('attendance_date', today())
                ->whereNotNull('check_in'));
    }

    private function applyCurrentSemester(Builder $query, bool $scopeSupervisor = true): void
    {
        $settings = app(GeneralSettings::class);

        $query
            ->where('semester', $settings->semester_type->value)
            ->where('year', $settings->year);

        if ($scopeSupervisor && $this->shouldScopeToSupervisor()) {
            $query->where('supervisor_id', auth()->id());
        }
    }

    private function canViewAdminStats(): bool
    {
        return auth()->user()?->hasAnyRole($this->adminStatRoles()) ?? false;
    }

    private function shouldScopeToSupervisor(): bool
    {
        return $this->canViewAdminStats()
            && ! auth()->user()?->hasAnyRole([
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN->value,
            ]);
    }

    private function adminStatRoles(): array
    {
        return [
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::PRACTICAL_TRAINING_SUPERVISOR->value,
            'Academic Supervisor',
            'University Supervisor',
        ];
    }

    private function genderDescription(Builder $query, string $profileRelation, string $distinctColumn): string
    {
        return __('Gender Breakdown', [
            'male' => $this->countByGender($query, $profileRelation, $distinctColumn, StudentGender::MALE),
            'female' => $this->countByGender($query, $profileRelation, $distinctColumn, StudentGender::FEMALE),
        ]);
    }

    private function countByGender(Builder $query, string $profileRelation, string $distinctColumn, StudentGender $gender): int
    {
        return (clone $query)
            ->whereHas($profileRelation, fn (Builder $profileQuery) => $profileQuery->where('gender', $gender->value))
            ->distinct($distinctColumn)
            ->count($distinctColumn);
    }

    private function countDistinct(Builder $query, string $column): int
    {
        return (clone $query)->distinct($column)->count($column);
    }

    private function studentCompaniesQuery(): Builder
    {
        return StudentCompany::query()
            ->where('student_id', auth()->id())
            ->whereHas('registration', fn (Builder $query) => $this->applyCurrentSemester($query, scopeSupervisor: false));
    }

    private function studentWorkingHours(): array
    {
        $studentCompanyIds = $this->studentCompaniesQuery()->pluck('id');

        return [
            'actual' => $this->actualWorkedHours($studentCompanyIds),
            'total' => $this->requiredWorkingHours(),
        ];
    }

    private function actualWorkedHours(Collection $studentCompanyIds): float
    {
        if ($studentCompanyIds->isEmpty()) {
            return 0.0;
        }

        $minutes = StudentAttendance::query()
            ->whereIn('student_company_id', $studentCompanyIds)
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->get(['check_in', 'check_out'])
            ->sum(fn (StudentAttendance $attendance) => $attendance->check_in->diffInMinutes($attendance->check_out));

        return round($minutes / 60, 2);
    }

    private function requiredWorkingHours(): float
    {
        $minutes = $this->studentCompaniesQuery()
            ->with('branch.workingHours')
            ->get()
            ->sum(fn (StudentCompany $studentCompany) => $this->requiredWorkingMinutes($studentCompany));

        return round($minutes / 60, 2);
    }

    private function requiredWorkingMinutes(StudentCompany $studentCompany): int
    {
        $branch = $studentCompany->branch;

        if (! $branch?->relationLoaded('workingHours')) {
            return 0;
        }

        $settings = app(GeneralSettings::class);
        $date = $settings->start_semester->copy()->startOfDay();
        $endDate = $settings->end_semester->copy()->startOfDay();
        $minutes = 0;

        while ($date->lte($endDate)) {
            $workingHour = $branch->workingHours->first(
                fn ($workingHour) => $workingHour->day?->value === $this->weekDayValue($date)
            );

            if ($workingHour && ! $workingHour->is_closed && $workingHour->start_time && $workingHour->end_time) {
                $minutes += (int) $workingHour->start_time->diffInMinutes($workingHour->end_time);
            }

            $date->addDay();
        }

        return $minutes;
    }

    private function weekDayValue(Carbon $date): int
    {
        return (($date->dayOfWeek + 1) % 7) + 1;
    }

    private function studentAttendanceDays(): int
    {
        $studentCompanyIds = $this->studentCompaniesQuery()->pluck('id');

        if ($studentCompanyIds->isEmpty()) {
            return 0;
        }

        return StudentAttendance::query()
            ->whereIn('student_company_id', $studentCompanyIds)
            ->whereNotNull('check_in')
            ->distinct('attendance_date')
            ->count('attendance_date');
    }

    private function studentLeaveRequestsCount(): int
    {
        $studentCompanyIds = $this->studentCompaniesQuery()->pluck('id');

        if ($studentCompanyIds->isEmpty()) {
            return 0;
        }

        return LeaveRequest::query()
            ->whereIn('student_company_id', $studentCompanyIds)
            ->count();
    }

    private function formatHours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2), '0'), '.');
    }
}

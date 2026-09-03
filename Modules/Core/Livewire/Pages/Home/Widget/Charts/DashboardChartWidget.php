<?php

namespace Modules\Core\Livewire\Pages\Home\Widget\Charts;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Settings\GeneralSettings;

abstract class DashboardChartWidget extends ChartWidget
{
    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '300px';

    protected int | string | array $columnSpan = 1;

    public function getHeading(): string | Htmlable | null
    {
        return static::$heading ? __(static::$heading) : null;
    }

    protected function getCachedData(): array
    {
        if (! static::canView()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        return parent::getCachedData();
    }

    protected static function canAny(array $permissions): bool
    {
        $user = auth()->user();

        return $user && collect($permissions)->contains(fn (string $permission) => $user->can($permission));
    }

    protected static function isStudent(): bool
    {
        return auth()->user()?->hasRole(UserRole::STUDENT->value) ?? false;
    }

    protected function currentRegistrationsQuery(bool $scopeSupervisor = true): Builder
    {
        return Registration::query()
            ->where(fn (Builder $query) => $this->applyCurrentSemester($query, $scopeSupervisor));
    }

    protected function currentStudentCompaniesQuery(bool $scopeSupervisor = true): Builder
    {
        return StudentCompany::query()
            ->whereHas('registration', fn (Builder $query) => $this->applyCurrentSemester($query, $scopeSupervisor));
    }

    protected function scopedStudentCompaniesQuery(): Builder
    {
        return StudentCompany::query()
            ->where(fn (Builder $query) => $this->applyStudentCompanyScope($query));
    }

    protected function scopedAttendanceQuery(): Builder
    {
        return StudentAttendance::query()
            ->whereHas('studentCompany', fn (Builder $query) => $this->applyStudentCompanyScope($query));
    }

    protected function scopedLeaveRequestsQuery(): Builder
    {
        return LeaveRequest::query()
            ->whereHas('studentCompany', fn (Builder $query) => $this->applyStudentCompanyScope($query));
    }

    protected function scopedFieldVisitsQuery(): Builder
    {
        return FieldVisit::query()
            ->whereHas('studentCompany', fn (Builder $query) => $this->applyStudentCompanyScope($query));
    }

    protected function applyStudentCompanyScope(Builder $query): void
    {
        if (static::isStudent()) {
            $query
                ->where('student_id', auth()->id())
                ->whereHas('registration', fn (Builder $registrationQuery) => $this->applyCurrentSemester($registrationQuery, false));

            return;
        }

        $query->whereHas('registration', fn (Builder $registrationQuery) => $this->applyCurrentSemester($registrationQuery));
    }

    protected function applyCurrentSemester(Builder $query, bool $scopeSupervisor = true): void
    {
        $settings = app(GeneralSettings::class);

        $query
            ->where('semester', $settings->semester_type->value)
            ->where('year', $settings->year);

        if ($scopeSupervisor && $this->shouldScopeToSupervisor()) {
            $query->where('supervisor_id', auth()->id());
        }
    }

    protected function shouldScopeToSupervisor(): bool
    {
        return auth()->user()?->hasRole(UserRole::PRACTICAL_TRAINING_SUPERVISOR->value)
            && ! auth()->user()?->hasAnyRole([
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN->value,
            ]);
    }

    protected function chartColors(int $count): array
    {
        return array_slice([
            '#2563eb',
            '#16a34a',
            '#f59e0b',
            '#dc2626',
            '#7c3aed',
            '#0891b2',
            '#db2777',
            '#65a30d',
            '#9333ea',
            '#ea580c',
        ], 0, $count);
    }

    protected function lastDays(int $days): array
    {
        return collect(range($days - 1, 0))
            ->map(fn (int $daysAgo) => now()->subDays($daysAgo)->startOfDay())
            ->all();
    }

    protected function monthStarts(int $months): array
    {
        return collect(range($months - 1, 0))
            ->map(fn (int $monthsAgo) => now()->subMonthsNoOverflow($monthsAgo)->startOfMonth())
            ->all();
    }

    protected function dayLabel(Carbon $date): string
    {
        return $date->translatedFormat('d M');
    }

    protected function monthLabel(Carbon $date): string
    {
        return $date->translatedFormat('M Y');
    }
}

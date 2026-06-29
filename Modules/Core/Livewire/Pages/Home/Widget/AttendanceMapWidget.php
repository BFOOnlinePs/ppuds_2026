<?php

namespace Modules\Core\Livewire\Pages\Home\Widget;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;

class AttendanceMapWidget extends Component
{
    private const MODAL_ID = 'attendance-map-modal';

    public bool $isOpen = false;

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public function mount(): void
    {
        $this->setDefaultDates();
    }

    #[On('open-attendance-map')]
    public function open(): void
    {
        if (! $this->canView()) {
            return;
        }

        $this->setDefaultDates();
        $this->isOpen = true;
        $this->dispatch('open-modal', id: self::MODAL_ID);
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->dispatch('close-modal', id: self::MODAL_ID);
    }

    public function applyFilters(): void
    {
        [$this->fromDate, $this->toDate] = $this->normalizedDates();

        $this->dispatchAttendanceMapPoints();
    }

    public function loadAttendancePoints(): void
    {
        if (! $this->isOpen || ! $this->canView()) {
            return;
        }

        $this->dispatchAttendanceMapPoints();
    }

    private function dispatchAttendanceMapPoints(): void
    {
        $points = $this->attendancePoints();

        $this->dispatch(
            'attendance-map-points-updated',
            points: $points,
            center: $this->mapCenter($points),
        );
    }

    public function canView(): bool
    {
        $user = auth()->user();

        return $user
            && $this->isAdmin()
            && $user->can('Attendance Map View');
    }

    public function attendancePoints(): array
    {
        if (! $this->canView()) {
            return [];
        }

        [$fromDate, $toDate] = $this->normalizedDates();

        return $this->attendanceQuery($fromDate, $toDate)
            ->latest('attendance_date')
            ->get()
            ->map(fn (StudentAttendance $attendance) => $this->formatAttendancePoint($attendance))
            ->values()
            ->all();
    }

    public function mapCenter(?array $points = null): array
    {
        $points ??= $this->attendancePoints();

        if (! empty($points)) {
            return [
                'lat' => (float) $points[0]['lat'],
                'lng' => (float) $points[0]['lng'],
            ];
        }

        return [
            'lat' => 32.2211,
            'lng' => 35.2544,
        ];
    }

    private function attendanceQuery(string $fromDate, string $toDate): Builder
    {
        return StudentAttendance::query()
            ->select([
                'id',
                'student_company_id',
                'attendance_date',
                'check_in',
                'check_in_latitude',
                'check_in_longitude',
                'check_out',
                'check_out_latitude',
                'check_out_longitude',
            ])
            ->with([
                'studentCompany' => fn ($query) => $query
                    ->withTrashed()
                    ->select(['id', 'student_id', 'company_id', 'branch_id', 'department_id', 'registration_id']),
                'studentCompany.student:id,name',
                'studentCompany.company:id',
                'studentCompany.company.translations',
                'studentCompany.branch:id',
                'studentCompany.branch.translations',
            ])
            ->whereBetween('attendance_date', [$fromDate, $toDate])
            ->where(function (Builder $query) {
                $query
                    ->where(function (Builder $checkInQuery) {
                        $checkInQuery
                            ->whereNotNull('check_in_latitude')
                            ->whereNotNull('check_in_longitude');
                    })
                    ->orWhere(function (Builder $checkOutQuery) {
                        $checkOutQuery
                            ->whereNotNull('check_out_latitude')
                            ->whereNotNull('check_out_longitude');
                    });
            })
            ->whereHas('studentCompany', function (Builder $query) {
                $query->withTrashed();
                $this->applyStudentCompanyScope($query);
            });
    }

    private function applyStudentCompanyScope(Builder $query): void
    {
        $user = auth()->user();

        if ($user?->hasRole(UserRole::STUDENT->value)) {
            $query->where('student_id', $user->id);

            return;
        }

        if ($this->shouldScopePracticalSupervisor()) {
            $query->whereHas('registration', fn (Builder $registrationQuery) => $registrationQuery->where('supervisor_id', $user->id));
        }

        if ($this->shouldScopeCompanySupervisor()) {
            $this->scopeCompanySupervisor($query);
        }
    }

    private function shouldScopePracticalSupervisor(): bool
    {
        return auth()->user()?->hasRole(UserRole::PRACTICAL_TRAINING_SUPERVISOR->value)
            && ! $this->isAdmin();
    }

    private function shouldScopeCompanySupervisor(): bool
    {
        return auth()->user()?->hasRole(UserRole::COMPANY_SUPERVISOR->value)
            && ! $this->isAdmin();
    }

    private function isAdmin(): bool
    {
        return auth()->user()?->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ]) ?? false;
    }

    private function scopeCompanySupervisor(Builder $query): void
    {
        $studentCompanyTable = (new StudentCompany())->getTable();

        $query->whereExists(function ($subQuery) use ($studentCompanyTable) {
            $subQuery
                ->selectRaw('1')
                ->from('ppu_ds_branch_department')
                ->whereColumn('ppu_ds_branch_department.branch_id', "{$studentCompanyTable}.branch_id")
                ->whereColumn('ppu_ds_branch_department.company_department_id', "{$studentCompanyTable}.department_id")
                ->where('ppu_ds_branch_department.user_id', auth()->id());
        });
    }

    private function formatAttendancePoint(StudentAttendance $attendance): array
    {
        $studentCompany = $attendance->studentCompany;
        $student = $studentCompany?->student;
        $coordinates = $this->attendanceCoordinates($attendance);

        return [
            'id' => $attendance->id,
            'lat' => $coordinates['lat'],
            'lng' => $coordinates['lng'],
            'student' => $student?->name ?? '-',
            'student_url' => $student && auth()->user()?->can('Student Details List')
                ? route('students.details', $student->id)
                : null,
            'company' => $studentCompany?->company?->name ?? '-',
            'branch' => $studentCompany?->branch?->name ?? '-',
            'date' => $attendance->attendance_date?->format('Y-m-d') ?? '-',
            'check_in' => $attendance->check_in?->format('H:i') ?? '-',
            'check_out' => $attendance->check_out?->format('H:i') ?? '-',
            'status' => $this->attendanceStateLabel($attendance),
            'color' => $this->attendanceStateColor($attendance),
        ];
    }

    private function attendanceCoordinates(StudentAttendance $attendance): array
    {
        if ($attendance->check_in_latitude !== null && $attendance->check_in_longitude !== null) {
            return [
                'lat' => (float) $attendance->check_in_latitude,
                'lng' => (float) $attendance->check_in_longitude,
            ];
        }

        return [
            'lat' => (float) $attendance->check_out_latitude,
            'lng' => (float) $attendance->check_out_longitude,
        ];
    }

    private function attendanceStateLabel(StudentAttendance $attendance): string
    {
        return $attendance->check_out
            ? __('Checked In And Out')
            : __('Checked In Only');
    }

    private function attendanceStateColor(StudentAttendance $attendance): string
    {
        return $attendance->check_out ? '#16a34a' : '#dc2626';
    }

    private function setDefaultDates(): void
    {
        $today = now()->toDateString();

        $this->fromDate ??= $today;
        $this->toDate ??= $today;
    }

    private function normalizedDates(): array
    {
        $fromDate = $this->normalizeDate($this->fromDate);
        $toDate = $this->normalizeDate($this->toDate);

        if (Carbon::parse($fromDate)->gt(Carbon::parse($toDate))) {
            return [$toDate, $fromDate];
        }

        return [$fromDate, $toDate];
    }

    private function normalizeDate(?string $date): string
    {
        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    public function render()
    {
        return view('core::livewire.pages.home.widget.attendance-map-widget');
    }
}

<?php

namespace Modules\Core\Livewire\Pages\Home\Widget;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Masmerise\Toaster\Toaster;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings;

/**
 * طلاب الفصل الحالي الذين لا يراهم أي مشرف شركة. مشرف الشركة يرى الطلاب عبر
 * مقعد (فرع + قسم ← مشرف) في branch_department، فإن كان المقعد مفقوداً أو
 * فارغاً أو يشغله مشرف محذوف ضاع الطالب.
 *
 * الإسناد يُجلس أحد مشرفي نفس الشركة في ذلك المقعد، فيظهر له كل طلاب القسم
 * فوراً دون تعديل سجلات التدريب نفسها.
 */
class UnsupervisedStudentsWidget extends Widget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'core::livewire.pages.home.widget.unsupervised-students-widget';

    private const PERMISSION = 'Company Update';

    /** @var array<string, int|string|null> مفتاح المجموعة ← المشرف المختار */
    public array $selectedSupervisors = [];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can(self::PERMISSION);
    }

    /**
     * مجموعة لكل (شركة + فرع + قسم) فيها طلاب بلا مشرف.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function groups(): Collection
    {
        if (! self::canView()) {
            return collect();
        }

        $settings = app(GeneralSettings::class);
        $pivotTable = config('ppuds.table_prefix').'branch_department';
        $table = (new StudentCompany)->getTable();

        return StudentCompany::query()
            ->where("{$table}.status", TrainingStatus::AVAILABLE->value)
            ->whereNotNull("{$table}.company_id")
            ->whereNotNull("{$table}.branch_id")
            ->whereNotNull("{$table}.department_id")
            ->whereHas('registration', fn (Builder $query): Builder => $query
                ->where('year', $settings->year)
                ->where('semester', $settings->semester_type->value))
            ->latestPerRegistration()
            // لا مقعد بمشرف فعّال: المقعد مفقود، أو مشرفه فارغ، أو محذوف حذفاً ناعماً.
            ->whereNotExists(fn ($subQuery) => $subQuery
                ->select(DB::raw(1))
                ->from("{$pivotTable} as seat")
                ->join('users as seat_user', 'seat_user.id', '=', 'seat.user_id')
                ->whereNull('seat_user.deleted_at')
                ->whereColumn('seat.branch_id', "{$table}.branch_id")
                ->whereColumn('seat.company_department_id', "{$table}.department_id"))
            ->with(['student', 'company.branches.supervisors', 'branch', 'department'])
            ->get()
            ->groupBy(fn (StudentCompany $placement): string => "{$placement->company_id}-{$placement->branch_id}-{$placement->department_id}")
            ->map(function (Collection $placements, string $key): array {
                $first = $placements->first();
                $supervisors = $first->company?->companySupervisors() ?? collect();

                // مشرف وحيد في الشركة يُختار مسبقاً، فيصبح الإسناد ضغطة واحدة.
                if ($supervisors->count() === 1 && blank($this->selectedSupervisors[$key] ?? null)) {
                    $this->selectedSupervisors[$key] = $supervisors->first()->id;
                }

                return [
                    'key' => $key,
                    'company' => $first->company?->name,
                    'branch' => $first->branch?->name,
                    'department' => $first->department?->name,
                    'students' => $placements->map(fn (StudentCompany $placement): ?string => $placement->student?->name)->filter()->values(),
                    'supervisors' => $supervisors->pluck('name', 'id'),
                ];
            })
            ->values();
    }

    public function assign(string $key): void
    {
        abort_unless(self::canView(), 403);

        [$companyId, $branchId, $departmentId] = array_map('intval', explode('-', $key) + [0, 0, 0]);
        $supervisorId = (int) ($this->selectedSupervisors[$key] ?? 0);

        if (! $supervisorId) {
            Toaster::error(__('Please select a supervisor.'));

            return;
        }

        $company = Company::find($companyId);

        // القيم تأتي من المتصفح: الفرع يجب أن يتبع الشركة، والمشرف من مشرفيها.
        abort_unless(
            $company
                && $company->branches()->whereKey($branchId)->exists()
                && CompanyDepartment::whereKey($departmentId)->exists()
                && $company->companySupervisors()->contains('id', $supervisorId),
            404
        );

        $this->seatSupervisor($branchId, $departmentId, $supervisorId);

        unset($this->selectedSupervisors[$key]);

        Toaster::success(__('Supervisor updated successfully'));
    }

    /**
     * نفس منطق شاشة مشرفي الشركات: مقعد واحد لكل (فرع + قسم)، نُحدِّثه إن
     * وُجد وننشئه إن كان مفقوداً.
     */
    protected function seatSupervisor(int $branchId, int $departmentId, int $userId): void
    {
        $pivot = config('ppuds.table_prefix').'branch_department';

        $exists = DB::table($pivot)
            ->where('branch_id', $branchId)
            ->where('company_department_id', $departmentId)
            ->exists();

        if ($exists) {
            DB::table($pivot)
                ->where('branch_id', $branchId)
                ->where('company_department_id', $departmentId)
                ->update(['user_id' => $userId, 'updated_at' => now()]);

            return;
        }

        DB::table($pivot)->insert([
            'branch_id' => $branchId,
            'company_department_id' => $departmentId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

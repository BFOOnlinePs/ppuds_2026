<?php

namespace Modules\Core\Livewire\Pages\Home\Widget;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\ManagesCompanySupervisorSeats;

/**
 * تنبيهات الصفحة الرئيسية لتدريبات الفصل الحالي التي لا يراها أي مشرف شركة.
 * مشرف الشركة يرى الطلاب عبر مقعد (فرع + قسم ← مشرف) في branch_department:
 *
 * 1. تدريب بلا قسم أو فرع، أو قسمه/فرعه محذوف: يُسند الطالب لمقعد أحد مشرفي
 *    شركته، أي يأخذ فرع ذلك المشرف وقسمه.
 * 2. تدريب في قسم سليم لكن بلا مشرف: يُجلس أحد مشرفي الشركة في ذلك القسم.
 */
class UnsupervisedStudentsWidget extends Widget
{
    use ManagesCompanySupervisorSeats;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'core::livewire.pages.home.widget.unsupervised-students-widget';

    private const PERMISSION = 'Company Update';

    /** @var array<string, int|string|null> مفتاح المجموعة ← المشرف المختار (قائمة 2) */
    public array $selectedSupervisors = [];

    /** @var array<int, int|string|null> الشركة ← مقعد المشرف المختار (قائمة 1) */
    public array $selectedSeats = [];

    /** @var array<int, array<int, string>> الشركة ← التدريبات المحددة (قائمة 1) */
    public array $selectedStudents = [];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can(self::PERMISSION);
    }

    // ===================== 1. طلاب بلا قسم أو في قسم محذوف =====================

    /**
     * مجموعة لكل شركة: طلابها غير المسندين، ومقاعد مشرفيها للاختيار منها.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function unassignedGroups(): Collection
    {
        if (! self::canView()) {
            return collect();
        }

        $placements = $this->unassignedPlacementsQuery()
            ->with(['student.studentProfile', 'company', 'branch', 'department'])
            ->get();

        $seatOptions = $this->companySeatOptions($placements->pluck('company_id')->unique()->values()->all());

        return $placements
            ->groupBy('company_id')
            ->map(function (Collection $companyPlacements, int|string $companyId) use ($seatOptions): array {
                $companyId = (int) $companyId;
                $seats = $seatOptions->get($companyId, collect());
                $ids = $companyPlacements->pluck('id')->map(fn ($id): string => (string) $id)->all();

                // كل الطلاب محددون افتراضياً، ويبقى اختيار المستخدم إن غيّره.
                $this->selectedStudents[$companyId] = array_key_exists($companyId, $this->selectedStudents)
                    ? array_values(array_intersect($this->selectedStudents[$companyId], $ids))
                    : $ids;

                // مشرف وحيد في الشركة يُختار مسبقاً، فيصبح الإسناد ضغطة واحدة.
                if ($seats->count() === 1 && blank($this->selectedSeats[$companyId] ?? null)) {
                    $this->selectedSeats[$companyId] = $seats->keys()->first();
                }

                return [
                    'company_id' => $companyId,
                    'company' => $companyPlacements->first()->company?->name,
                    'students' => $companyPlacements->map(fn (StudentCompany $placement): array => [
                        'id' => (string) $placement->id,
                        'name' => $placement->student?->name,
                        'number' => $placement->student?->studentProfile?->student_number,
                        'branch' => $placement->branch?->name,
                        'reasons' => $this->unassignedReasons($placement),
                    ])->values(),
                    'seats' => $seats,
                ];
            })
            ->values();
    }

    public function assignStudents(int $companyId): void
    {
        abort_unless(self::canView(), 403);

        $seatId = (int) ($this->selectedSeats[$companyId] ?? 0);
        $placementIds = array_map('intval', $this->selectedStudents[$companyId] ?? []);

        if (! $seatId) {
            Toaster::error(__('Please select a supervisor.'));

            return;
        }

        if ($placementIds === []) {
            Toaster::error(__('Please select at least one student.'));

            return;
        }

        // القيم تأتي من المتصفح: المقعد من مقاعد هذه الشركة، والتدريبات من غير المسندين فيها.
        $seat = $this->companySeatsQuery([$companyId])->where('seat.id', $seatId)->first();

        abort_unless($seat, 404);

        $placements = $this->unassignedPlacementsQuery()
            ->where((new StudentCompany)->qualifyColumn('company_id'), $companyId)
            ->whereKey($placementIds)
            ->get();

        // تحديث عبر النموذج ليُسجَّل في سجل النشاط كأي تعديل على التدريب.
        foreach ($placements as $placement) {
            $placement->update([
                'branch_id' => $seat->branch_id,
                'department_id' => $seat->company_department_id,
            ]);
        }

        unset($this->selectedSeats[$companyId], $this->selectedStudents[$companyId]);

        Toaster::success(__(':count students assigned to :name', [
            'count' => $placements->count(),
            'name' => $seat->supervisor_name,
        ]));
    }

    /**
     * تدريبات الفصل الحالي القائمة التي بلا قسم أو فرع، أو قسمها أو فرعها
     * محذوف حذفاً ناعماً (العلاقة لا تعيد المحذوف).
     */
    protected function unassignedPlacementsQuery(): Builder
    {
        $settings = app(GeneralSettings::class);
        $table = (new StudentCompany)->getTable();

        return StudentCompany::query()
            ->where("{$table}.status", TrainingStatus::AVAILABLE->value)
            ->whereHas('company')
            ->whereHas('registration', fn (Builder $query): Builder => $query
                ->where('year', $settings->year)
                ->where('semester', $settings->semester_type->value))
            ->latestPerRegistration()
            ->where(fn (Builder $query): Builder => $query
                ->whereDoesntHave('department')
                ->orWhereDoesntHave('branch'));
    }

    /**
     * @return array<int, string>
     */
    protected function unassignedReasons(StudentCompany $placement): array
    {
        $reasons = [];

        if (! $placement->department) {
            $reasons[] = $placement->department_id ? __('Department deleted') : __('No department');
        }

        if (! $placement->branch) {
            $reasons[] = $placement->branch_id ? __('Branch deleted') : __('No branch');
        }

        return $reasons;
    }

    /**
     * خيارات «المشرف — الفرع › القسم» لكل شركة.
     *
     * @param  array<int, int>  $companyIds
     * @return Collection<int, Collection<int, string>>
     */
    protected function companySeatOptions(array $companyIds): Collection
    {
        if ($companyIds === []) {
            return collect();
        }

        return $this->companySeatsQuery($companyIds)
            ->orderBy('supervisor.name')
            ->get()
            ->groupBy('company_id')
            // جدول المقاعد قد يحمل صفين لنفس (المشرف + الفرع + القسم)، فيظهر الخيار مرة واحدة.
            ->map(fn (Collection $seats): Collection => $seats->unique(
                fn (object $seat): string => "{$seat->user_id}-{$seat->branch_id}-{$seat->company_department_id}"
            )->mapWithKeys(fn (object $seat): array => [
                (int) $seat->id => ($seat->supervisor_name ?: '—').' — '.($seat->branch_name ?: '—').' › '.($seat->department_name ?: '—'),
            ]));
    }

    /**
     * مقاعد صالحة في الشركات: مشرف فعّال، وفرع تابع للشركة غير محذوف، وقسم غير محذوف.
     *
     * @param  array<int, int>  $companyIds
     */
    protected function companySeatsQuery(array $companyIds): QueryBuilder
    {
        $prefix = config('ppuds.table_prefix');
        $locale = app()->getLocale();

        return DB::table("{$prefix}branch_department as seat")
            ->join('users as supervisor', 'supervisor.id', '=', 'seat.user_id')
            ->whereNull('supervisor.deleted_at')
            ->join("{$prefix}branch_company as link", 'link.branch_id', '=', 'seat.branch_id')
            ->join((new Branch)->getTable().' as branch', 'branch.id', '=', 'seat.branch_id')
            ->whereNull('branch.deleted_at')
            ->join((new CompanyDepartment)->getTable().' as department', 'department.id', '=', 'seat.company_department_id')
            ->whereNull('department.deleted_at')
            ->leftJoin('branch_branch_translations as bt', function ($join) use ($locale): void {
                $join->on('bt.branch_id', '=', 'seat.branch_id')->where('bt.locale', '=', $locale);
            })
            ->leftJoin("{$prefix}company_department_translations as dt", function ($join) use ($locale): void {
                $join->on('dt.department_id', '=', 'seat.company_department_id')->where('dt.locale', '=', $locale);
            })
            ->whereIn('link.company_id', $companyIds)
            ->select([
                'seat.id',
                'seat.branch_id',
                'seat.company_department_id',
                'seat.user_id',
                'link.company_id',
                'supervisor.name as supervisor_name',
                'bt.name as branch_name',
                'dt.name as department_name',
            ]);
    }

    // ===================== 2. طلاب في قسم بلا مشرف =====================

    /**
     * مجموعة لكل (شركة + فرع + قسم) سليمين فيها طلاب بلا مشرف.
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
            // القسم أو الفرع المحذوف يظهر في القائمة الأولى لا هنا.
            ->whereHas('department')
            ->whereHas('branch')
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
}

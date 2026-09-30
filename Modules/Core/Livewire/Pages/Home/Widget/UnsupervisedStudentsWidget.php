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
 * مشرف الشركة يرى الطلاب عبر مقعد (فرع + قسم ← مشرف) في branch_department،
 * والهدف أن ينتهي كل تدريب فيه مشكلة عند مشرف شركة:
 *
 * 1. تدريب شركته أو فرعه أو قسمه مفقود أو محذوف: يُنقل الطلاب إلى مقعد مشرف
 *    يُختار (شركته وفرعه وقسمه). سجل الطالب مربوط بالتدريب فينتقل معه.
 * 2. تدريب في قسم سليم لكن بلا مشرف: يُجلس أحد مشرفي الشركة في القسم نفسه،
 *    ولا تتغير بيانات الطلاب.
 */
class UnsupervisedStudentsWidget extends Widget
{
    use ManagesCompanySupervisorSeats;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'core::livewire.pages.home.widget.unsupervised-students-widget';

    private const PERMISSION = 'Company Update';

    /** @var array<string, int|string|null> مفتاح المجموعة ← المشرف المختار (قائمة 2) */
    public array $selectedSupervisors = [];

    /** @var array<string, int|string|null> مفتاح المجموعة ← الشركة المختارة (قائمة 1) */
    public array $selectedCompanies = [];

    /** @var array<string, int|string|null> مفتاح المجموعة ← مقعد المشرف المختار (قائمة 1) */
    public array $selectedSeats = [];

    /** @var array<string, array<int, string>> مفتاح المجموعة ← التدريبات المحددة (قائمة 1) */
    public array $selectedStudents = [];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can(self::PERMISSION);
    }

    // ===================== 1. تدريبات بشركة أو فرع أو قسم مفقود أو محذوف =====================

    /**
     * مجموعة لكل (شركة + فرع + قسم) كما هي مسجلة على التدريب، حتى يُنقل القسم كاملاً.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function brokenGroups(): Collection
    {
        if (! self::canView()) {
            return collect();
        }

        $placements = $this->brokenPlacementsQuery()
            // المحذوف يُحمَّل ليظهر اسمه ويُعرف سبب المشكلة.
            ->with([
                'student.studentProfile',
                'company' => fn ($query) => $query->withTrashed(),
                'branch' => fn ($query) => $query->withTrashed(),
                'department' => fn ($query) => $query->withTrashed(),
            ])
            ->get();

        $groups = $placements
            ->groupBy(fn (StudentCompany $placement): string => $this->groupKey($placement))
            ->map(function (Collection $groupPlacements, string $key): array {
                $first = $groupPlacements->first();
                $ids = $groupPlacements->pluck('id')->map(fn ($id): string => (string) $id)->all();

                // كل الطلاب محددون افتراضياً، ويبقى اختيار المستخدم إن غيّره.
                $this->selectedStudents[$key] = array_key_exists($key, $this->selectedStudents)
                    ? array_values(array_intersect($this->selectedStudents[$key], $ids))
                    : $ids;

                // الاقتراح مرة واحدة فقط، فلا يعود إن مسحه المستخدم.
                if (! array_key_exists($key, $this->selectedCompanies)) {
                    $this->selectedCompanies[$key] = $this->suggestedCompanyId($first->company);
                }

                return [
                    'key' => $key,
                    'company' => $first->company?->name,
                    'branch' => $first->branch?->name,
                    'department' => $first->department?->name,
                    'reasons' => $this->brokenReasons($first),
                    'students' => $groupPlacements->map(fn (StudentCompany $placement): array => [
                        'id' => (string) $placement->id,
                        'name' => $placement->student?->name,
                        'number' => $placement->student?->studentProfile?->student_number,
                    ])->values(),
                ];
            });

        $seatOptions = $this->companySeatOptions(
            collect($this->selectedCompanies)->only($groups->keys())->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all()
        );

        return $groups
            ->map(function (array $group) use ($seatOptions): array {
                $seats = $seatOptions->get((int) ($this->selectedCompanies[$group['key']] ?? 0), collect());

                // مشرف وحيد في الشركة المختارة يُختار مسبقاً، فيصبح النقل ضغطة واحدة.
                if ($seats->count() === 1 && blank($this->selectedSeats[$group['key']] ?? null)) {
                    $this->selectedSeats[$group['key']] = $seats->keys()->first();
                }

                return $group + ['seats' => $seats];
            })
            ->values();
    }

    /**
     * كل الشركات الفعّالة لاختيار الشركة الجديدة.
     *
     * @return Collection<int, string>
     */
    public function companyOptions(): Collection
    {
        return Company::query()->with('translations')->get()->pluck('name', 'id')->sort();
    }

    /** تغيير الشركة يُسقط المشرف المختار لأنه من شركة أخرى. */
    public function updatedSelectedCompanies(mixed $value, string $key): void
    {
        unset($this->selectedSeats[$key]);
    }

    public function moveGroup(string $key): void
    {
        abort_unless(self::canView(), 403);

        $companyId = (int) ($this->selectedCompanies[$key] ?? 0);
        $seatId = (int) ($this->selectedSeats[$key] ?? 0);
        $placementIds = array_map('intval', $this->selectedStudents[$key] ?? []);

        if (! $companyId || ! $seatId) {
            Toaster::error(__('Please select a supervisor.'));

            return;
        }

        if ($placementIds === []) {
            Toaster::error(__('Please select at least one student.'));

            return;
        }

        // القيم تأتي من المتصفح: المقعد من مقاعد الشركة المختارة الصالحة،
        // والتدريبات من المجموعة نفسها وما زالت فيها مشكلة.
        $seat = $this->companySeatsQuery([$companyId])->where('seat.id', $seatId)->first();

        abort_unless($seat, 404);

        $placements = $this->brokenPlacementsQuery()
            ->whereKey($placementIds)
            ->get()
            ->filter(fn (StudentCompany $placement): bool => $this->groupKey($placement) === $key);

        // تحديث عبر النموذج ليُسجَّل في سجل النشاط كأي تعديل على التدريب.
        foreach ($placements as $placement) {
            $placement->update([
                'company_id' => $companyId,
                'branch_id' => $seat->branch_id,
                'department_id' => $seat->company_department_id,
            ]);
        }

        unset($this->selectedCompanies[$key], $this->selectedSeats[$key], $this->selectedStudents[$key]);

        Toaster::success(__(':count students assigned to :name', [
            'count' => $placements->count(),
            'name' => $seat->supervisor_name,
        ]));
    }

    /**
     * تدريبات الفصل الحالي القائمة التي بلا شركة أو فرع أو قسم، أو أحدها محذوف
     * حذفاً ناعماً (العلاقة لا تعيد المحذوف).
     */
    protected function brokenPlacementsQuery(): Builder
    {
        return $this->currentPlacementsQuery()
            ->where(fn (Builder $query): Builder => $query
                ->whereDoesntHave('company')
                ->orWhereDoesntHave('branch')
                ->orWhereDoesntHave('department'));
    }

    protected function groupKey(StudentCompany $placement): string
    {
        return (int) $placement->company_id.'-'.(int) $placement->branch_id.'-'.(int) $placement->department_id;
    }

    /**
     * الشركة نفسها إن بقيت، وإلا شركة فعّالة بنفس اسم المحذوفة (غالباً أُعيد إنشاؤها).
     */
    protected function suggestedCompanyId(?Company $company): ?int
    {
        if (! $company) {
            return null;
        }

        if (! $company->trashed()) {
            return (int) $company->id;
        }

        $name = trim((string) $company->name);

        return $name === '' ? null : Company::query()->whereTranslation('name', $name)->value('id');
    }

    /**
     * @return array<int, string>
     */
    protected function brokenReasons(StudentCompany $placement): array
    {
        $reasons = [];

        foreach ([
            'company' => ['Company deleted', 'No company'],
            'branch' => ['Branch deleted', 'No branch'],
            'department' => ['Department deleted', 'No department'],
        ] as $relation => [$deleted, $missing]) {
            $related = $placement->{$relation};

            if (! $related) {
                $reasons[] = __($missing);
            } elseif ($related->trashed()) {
                $reasons[] = __($deleted);
            }
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
            ->join((new Company)->getTable().' as company', 'company.id', '=', 'link.company_id')
            ->whereNull('company.deleted_at')
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

    /**
     * تدريبات الفصل الحالي القائمة، آخر تدريب لكل تسجيل.
     */
    protected function currentPlacementsQuery(): Builder
    {
        $settings = app(GeneralSettings::class);
        $table = (new StudentCompany)->getTable();

        return StudentCompany::query()
            ->where("{$table}.status", TrainingStatus::AVAILABLE->value)
            ->whereHas('registration', fn (Builder $query): Builder => $query
                ->where('year', $settings->year)
                ->where('semester', $settings->semester_type->value))
            ->latestPerRegistration();
    }

    // ===================== 2. طلاب في قسم سليم بلا مشرف =====================

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

        $pivotTable = config('ppuds.table_prefix').'branch_department';
        $table = (new StudentCompany)->getTable();

        return $this->currentPlacementsQuery()
            // الشركة أو الفرع أو القسم المفقود/المحذوف يظهر في القائمة الأولى لا هنا.
            ->whereHas('company')
            ->whereHas('branch')
            ->whereHas('department')
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

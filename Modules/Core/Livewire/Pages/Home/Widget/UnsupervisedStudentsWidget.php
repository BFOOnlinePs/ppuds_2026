<?php

namespace Modules\Core\Livewire\Pages\Home\Widget;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
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
 * تنبيهات إسناد مشرفي الشركات في الصفحة الرئيسية. مشرف الشركة يرى الطلاب عبر
 * مقعد (فرع + قسم ← مشرف) في branch_department، ولذلك نوعان من الخلل:
 *
 * 1. طلاب لا يراهم أي مشرف: مقعد قسمهم مفقود، أو فارغ، أو يشغله مشرف محذوف.
 * 2. مشرفون مسندون لمقعد لا يوصل لشركة: القسم أو الفرع محذوف (حذفاً ناعماً لا
 *    يلمس المقاعد)، أو الفرع لم يعد تابعاً لأي شركة فعّالة.
 */
class UnsupervisedStudentsWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use ManagesCompanySupervisorSeats;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'core::livewire.pages.home.widget.unsupervised-students-widget';

    private const PERMISSION = 'Company Update';

    /** حد العرض لقائمة المشرفين، والباقي يظهر بعد إصلاح الأوائل. */
    private const BROKEN_ASSIGNMENTS_LIMIT = 50;

    /** @var array<string, int|string|null> مفتاح المجموعة ← المشرف المختار */
    public array $selectedSupervisors = [];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can(self::PERMISSION);
    }

    // ===================== طلاب بلا مشرف =====================

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

    // ===================== مشرفون بإسناد معطوب =====================

    /**
     * @return array{rows: Collection<int, array<string, mixed>>, total: int}
     */
    public function brokenAssignments(): array
    {
        if (! self::canView()) {
            return ['rows' => collect(), 'total' => 0];
        }

        $rows = (clone $this->brokenSeatsQuery())
            ->orderBy('supervisor.name')
            ->limit(self::BROKEN_ASSIGNMENTS_LIMIT)
            ->get()
            ->map(fn (object $seat): array => [
                'id' => (int) $seat->id,
                'supervisor' => $seat->supervisor_name,
                'branch' => $seat->branch_name,
                'department' => $seat->department_name,
                'reasons' => $this->brokenReasons($seat),
                'students_count' => (int) $seat->students_count,
            ]);

        return [
            'rows' => $rows,
            'total' => $rows->count() < self::BROKEN_ASSIGNMENTS_LIMIT
                ? $rows->count()
                : (clone $this->brokenSeatsQuery())->count(),
        ];
    }

    public function reassignBrokenAssignmentAction(): Action
    {
        return Action::make('reassignBrokenAssignment')
            ->label(__('Assign Department'))
            ->icon('solar-add-circle-bold-duotone')
            ->color('success')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => __('Assign Department To :name', [
                'name' => $this->brokenSeat($arguments)?->supervisor_name ?? '—',
            ]))
            ->modalDescription(__('The supervisor will immediately see every student in this department, with their full history.'))
            ->modalSubmitActionLabel(__('Assign'))
            ->modalWidth('2xl')
            ->form(fn (): array => $this->departmentPickerFields())
            // الشركة المعروفة تُملأ مسبقاً: شركة الفرع إن بقي تابعاً لها، وإلا شركة طلاب هذا المقعد.
            ->fillForm(function (array $arguments): array {
                $seat = $this->brokenSeat($arguments);

                return [
                    'company_id' => $seat?->active_company_id ?? $seat?->placement_company_id,
                    'branch_id' => $seat && $seat->active_company_id && ! $seat->branch_deleted ? $seat->branch_id : null,
                ];
            })
            ->visible(fn (): bool => self::canView())
            ->action(function (array $data, array $arguments): void {
                abort_unless(self::canView(), 403);

                $seat = $this->brokenSeat($arguments);

                if (! $seat) {
                    Toaster::error(__('No records found.'));

                    return;
                }

                $this->seatSupervisor((int) $data['branch_id'], (int) $data['department_id'], (int) $seat->user_id);

                // المقعد المعطوب يُحذف إن لم يكن عليه طلاب، وإلا يبقى حتى لا يختفوا عن المشرف.
                if ((int) $seat->students_count === 0) {
                    DB::table(config('ppuds.table_prefix').'branch_department')->where('id', $seat->id)->delete();
                } else {
                    Toaster::warning(__('The old assignment was kept because :count students are placed on it.', [
                        'count' => (int) $seat->students_count,
                    ]));
                }

                Toaster::success(__('Department assigned successfully'));

                $this->sendSupervisorToUniversity((int) $data['company_id'], (int) $seat->user_id, null);
            });
    }

    public function removeBrokenAssignmentAction(): Action
    {
        return Action::make('removeBrokenAssignment')
            ->label(__('Remove Assignment'))
            ->icon('solar-minus-circle-bold-duotone')
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(__('Remove Assignment'))
            ->modalSubmitActionLabel(__('Remove'))
            ->visible(fn (): bool => self::canView())
            ->action(function (array $arguments): void {
                abort_unless(self::canView(), 403);

                $seat = $this->brokenSeat($arguments);

                if (! $seat) {
                    Toaster::error(__('No records found.'));

                    return;
                }

                // لا يُحذف مقعد عليه طلاب، وإلا اختفوا عن مشرفهم.
                if ((int) $seat->students_count > 0) {
                    Toaster::error(__('The old assignment was kept because :count students are placed on it.', [
                        'count' => (int) $seat->students_count,
                    ]));

                    return;
                }

                DB::table(config('ppuds.table_prefix').'branch_department')->where('id', $seat->id)->delete();

                Toaster::success(__('Assignment removed successfully'));
            });
    }

    /**
     * المقعد من القائمة نفسها، فلا يمكن تمرير id مقعد سليم من المتصفح.
     */
    protected function brokenSeat(array $arguments): ?object
    {
        $seatId = (int) ($arguments['seat'] ?? 0);

        return $seatId ? (clone $this->brokenSeatsQuery())->where('seat.id', $seatId)->first() : null;
    }

    /**
     * @return array<int, string>
     */
    protected function brokenReasons(object $seat): array
    {
        $reasons = [];

        if ($seat->department_deleted) {
            $reasons[] = __('Department deleted');
        }

        if ($seat->branch_deleted) {
            $reasons[] = __('Branch deleted');
        }

        if (! $seat->active_company_id) {
            $reasons[] = (int) $seat->company_links_count > 0
                ? __('Company deleted')
                : __('Branch without a company');
        }

        return $reasons;
    }

    /**
     * مقاعد مشرفين فعّالين لا توصل لشركة: القسم أو الفرع محذوف، أو الفرع بلا
     * شركة فعّالة. أسماء الفرع والقسم تُقرأ من جداول الترجمة لأن المحذوف
     * حذفاً ناعماً لا تُعيده العلاقات.
     */
    protected function brokenSeatsQuery(): QueryBuilder
    {
        $prefix = config('ppuds.table_prefix');
        $locale = app()->getLocale();
        $branchesTable = (new Branch)->getTable();
        $departmentsTable = (new CompanyDepartment)->getTable();
        $companiesTable = (new Company)->getTable();
        $placementsTable = (new StudentCompany)->getTable();

        $activeCompanyLink = fn ($query) => $query
            ->from("{$prefix}branch_company as active_link")
            ->join("{$companiesTable} as active_company", 'active_company.id', '=', 'active_link.company_id')
            ->whereNull('active_company.deleted_at')
            ->whereColumn('active_link.branch_id', 'seat.branch_id');

        $seatPlacements = fn ($query, string $alias) => $query
            ->from("{$placementsTable} as {$alias}")
            ->whereColumn("{$alias}.branch_id", 'seat.branch_id')
            ->whereColumn("{$alias}.department_id", 'seat.company_department_id')
            ->whereNull("{$alias}.deleted_at");

        return DB::table("{$prefix}branch_department as seat")
            ->join('users as supervisor', 'supervisor.id', '=', 'seat.user_id')
            ->whereNull('supervisor.deleted_at')
            ->leftJoin("{$branchesTable} as branch", 'branch.id', '=', 'seat.branch_id')
            ->leftJoin("{$departmentsTable} as department", 'department.id', '=', 'seat.company_department_id')
            ->leftJoin('branch_branch_translations as bt', function ($join) use ($locale): void {
                $join->on('bt.branch_id', '=', 'seat.branch_id')->where('bt.locale', '=', $locale);
            })
            ->leftJoin("{$prefix}company_department_translations as dt", function ($join) use ($locale): void {
                $join->on('dt.department_id', '=', 'seat.company_department_id')->where('dt.locale', '=', $locale);
            })
            ->where(fn ($query) => $query
                ->whereNull('branch.id')
                ->orWhereNotNull('branch.deleted_at')
                ->orWhereNull('department.id')
                ->orWhereNotNull('department.deleted_at')
                ->orWhereNotExists(fn ($subQuery) => $activeCompanyLink($subQuery)->select(DB::raw(1))))
            ->select([
                'seat.id',
                'seat.branch_id',
                'seat.user_id',
                'supervisor.name as supervisor_name',
                'bt.name as branch_name',
                'dt.name as department_name',
            ])
            ->selectRaw('(branch.id is null or branch.deleted_at is not null) as branch_deleted')
            ->selectRaw('(department.id is null or department.deleted_at is not null) as department_deleted')
            ->selectSub(fn ($query) => $activeCompanyLink($query)->select('active_link.company_id')->limit(1), 'active_company_id')
            ->selectSub(fn ($query) => $query
                ->from("{$prefix}branch_company as any_link")
                ->whereColumn('any_link.branch_id', 'seat.branch_id')
                ->selectRaw('count(*)'), 'company_links_count')
            ->selectSub(fn ($query) => $seatPlacements($query, 'seat_placement')->selectRaw('count(*)'), 'students_count')
            ->selectSub(fn ($query) => $seatPlacements($query, 'seat_placement_company')->select('seat_placement_company.company_id')->limit(1), 'placement_company_id');
    }
}

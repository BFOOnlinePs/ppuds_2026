<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes branch/company links left behind by companies that no longer exist.
 *
 * These rows are harmless until a new company is created with an id one of
 * them points at: MySQL hands out recycled ids, and the new company silently
 * inherits the dead company's branches — and every department supervisor on
 * them. The same applies to department rows whose branch, department or user
 * is gone.
 *
 * Previews by default; pass --force to actually delete.
 */
class CleanOrphanCompanyBranchLinks extends Command
{
    protected $signature = 'ppuds:clean-orphan-company-links
        {--force : Delete the rows. Without it the command only reports what it would delete.}
        {--company= : Also drop links on this company that were created before the company itself (inherited rows).}
        {--include-used : Also drop links that student placements are recorded against. Off by default.}';

    protected $description = 'Delete branch/company and branch/department rows pointing at records that no longer exist.';

    public function handle(): int
    {
        $prefix = config('ppuds.table_prefix');
        $force = (bool) $this->option('force');

        $orphanLinkIds = $this->orphanBranchCompanyIds($prefix);
        $orphanDepartmentIds = $this->orphanBranchDepartmentIds($prefix);
        $inheritedIds = $this->inheritedLinkIds($prefix);

        $this->report('Branch links whose company no longer exists', $prefix.'branch_company', $orphanLinkIds);
        $this->report('Department rows whose branch, department or user no longer exists', $prefix.'branch_department', $orphanDepartmentIds);

        if ($this->option('company')) {
            $this->report(
                'Links on company '.$this->option('company').' created before the company itself',
                $prefix.'branch_company',
                $inheritedIds
            );
        }

        // A link is only safe to drop when no student placement stands on that
        // exact company+branch pair. Those are kept unless explicitly included.
        $usedLinkIds = $this->linkIdsCarryingPlacements($prefix, array_merge($orphanLinkIds, $inheritedIds));

        if ($usedLinkIds !== [] && ! $this->option('include-used')) {
            $orphanLinkIds = array_values(array_diff($orphanLinkIds, $usedLinkIds));
            $inheritedIds = array_values(array_diff($inheritedIds, $usedLinkIds));

            $this->newLine();
            $this->warn('Kept '.count($usedLinkIds).' link(s): student placements are recorded on that company and branch.');
            $this->line('Pass --include-used only if you know those placements should lose the branch.');
        }

        $total = count($orphanLinkIds) + count($orphanDepartmentIds) + count($inheritedIds);

        if ($total === 0) {
            $this->info('Nothing to clean.');

            return self::SUCCESS;
        }

        if (! $force) {
            $this->newLine();
            $this->warn("Preview only — {$total} row(s) would be deleted. Re-run with --force to apply.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($prefix, $orphanLinkIds, $orphanDepartmentIds, $inheritedIds): void {
            if ($orphanLinkIds !== []) {
                DB::table($prefix.'branch_company')->whereIn('id', $orphanLinkIds)->delete();
            }

            if ($orphanDepartmentIds !== []) {
                DB::table($prefix.'branch_department')->whereIn('id', $orphanDepartmentIds)->delete();
            }

            if ($inheritedIds !== []) {
                DB::table($prefix.'branch_company')->whereIn('id', $inheritedIds)->delete();
            }
        });

        $this->newLine();
        $this->info("Deleted {$total} row(s).");

        return self::SUCCESS;
    }

    /**
     * Of the given links, the ones a student placement is recorded against.
     * Dropping those would leave a placement pointing at a branch its company
     * no longer lists, so they are kept unless the caller insists.
     */
    private function linkIdsCarryingPlacements(string $prefix, array $linkIds): array
    {
        if ($linkIds === []) {
            return [];
        }

        return DB::table($prefix.'branch_company as bc')
            ->join($prefix.'students_companies as sc', function ($join): void {
                $join->on('sc.company_id', '=', 'bc.company_id')
                    ->on('sc.branch_id', '=', 'bc.branch_id');
            })
            ->whereIn('bc.id', $linkIds)
            ->distinct()
            ->pluck('bc.id')
            ->all();
    }

    /** branch_company rows pointing at a company that is gone. */
    private function orphanBranchCompanyIds(string $prefix): array
    {
        return DB::table($prefix.'branch_company as bc')
            ->leftJoin($prefix.'companies as c', 'c.id', '=', 'bc.company_id')
            ->whereNull('c.id')
            ->pluck('bc.id')
            ->all();
    }

    /** branch_department rows whose branch, department or supervisor is gone. */
    private function orphanBranchDepartmentIds(string $prefix): array
    {
        return DB::table($prefix.'branch_department as bd')
            ->leftJoin('branch_branches as b', 'b.id', '=', 'bd.branch_id')
            ->leftJoin($prefix.'company_departments as d', 'd.id', '=', 'bd.company_department_id')
            ->leftJoin('users as u', 'u.id', '=', 'bd.user_id')
            ->where(fn ($query) => $query
                ->whereNull('b.id')
                ->orWhereNull('d.id')
                ->orWhereNull('u.id'))
            ->pluck('bd.id')
            ->all();
    }

    /**
     * Links a company could not have made itself: the row is older than the
     * company row, so it was inherited from whoever held the id before.
     */
    private function inheritedLinkIds(string $prefix): array
    {
        $companyId = $this->option('company');

        if (blank($companyId)) {
            return [];
        }

        $company = DB::table($prefix.'companies')->where('id', (int) $companyId)->first();

        if (! $company) {
            $this->error("Company {$companyId} not found.");

            return [];
        }

        return DB::table($prefix.'branch_company')
            ->where('company_id', (int) $companyId)
            ->where('created_at', '<', $company->created_at)
            ->pluck('id')
            ->all();
    }

    private function report(string $title, string $table, array $ids): void
    {
        $this->newLine();
        $this->line($title.': <fg=yellow>'.count($ids).'</> row(s)');

        if ($ids === []) {
            return;
        }

        $sample = DB::table($table)->whereIn('id', array_slice($ids, 0, 5))->get();

        $this->table(
            array_keys((array) $sample->first()),
            $sample->map(fn ($row): array => array_map(fn ($value): string => (string) $value, (array) $row))->all()
        );

        if (count($ids) > 5) {
            $this->line('  … and '.(count($ids) - 5).' more');
        }
    }
}

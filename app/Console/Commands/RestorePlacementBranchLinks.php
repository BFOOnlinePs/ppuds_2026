<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Entities\Branch;

/**
 * Re-links branches that student placements still point at.
 *
 * A placement stores company_id and branch_id side by side. When the branch is
 * detached from the company — an older save of the company screen dropped every
 * branch missing from its repeater — the placement keeps pointing at a branch
 * the company no longer lists. Its edit screen then finds no option for that id
 * and renders the raw branch id instead of the branch name.
 *
 * Previews by default; pass --force to insert the missing links.
 */
class RestorePlacementBranchLinks extends Command
{
    protected $signature = 'ppuds:restore-placement-branch-links
        {--company= : Limit to placements of this company.}
        {--force : Insert the missing links. Without it the command only reports what it would insert.}';

    protected $description = 'Re-link branches that student placements point at but their company no longer lists.';

    public function handle(): int
    {
        $rows = $this->placementsWithUnlinkedBranch();

        if ($rows->isEmpty()) {
            $this->info('Every student placement points at a branch its company still lists.');

            return self::SUCCESS;
        }

        // A deleted branch cannot be re-linked: the select filters it out anyway,
        // so those placements need a branch picked by hand.
        [$restorable, $deletedBranches] = $rows->partition(
            fn (object $row): bool => $row->branch_row_id !== null && $row->branch_deleted_at === null
        );

        $this->report('Placements whose branch can be re-linked', $restorable);
        $this->report('Placements whose branch no longer exists — pick a branch for these from the placement screen', $deletedBranches);

        $links = $restorable
            ->map(fn (object $row): array => [
                'company_id' => (int) $row->company_id,
                'branch_id' => (int) $row->branch_id,
            ])
            ->unique(fn (array $link): string => $link['company_id'].'-'.$link['branch_id'])
            ->values();

        if ($links->isEmpty()) {
            $this->newLine();
            $this->warn('Nothing to insert: every branch above is deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('Preview only — '.$links->count().' link(s) would be inserted. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        $this->insertLinks($links);

        $this->newLine();
        $this->info('Inserted '.$links->count().' link(s).');

        return self::SUCCESS;
    }

    /**
     * Placements whose company/branch pair has no row in the branch_company pivot.
     */
    private function placementsWithUnlinkedBranch(): Collection
    {
        $prefix = config('ppuds.table_prefix');
        $branchTable = config('branch.table_prefix').'branches';
        $companyId = $this->option('company');

        return DB::table($prefix.'students_companies as sc')
            ->leftJoin($prefix.'branch_company as bc', function ($join): void {
                $join->on('bc.company_id', '=', 'sc.company_id')
                    ->on('bc.branch_id', '=', 'sc.branch_id');
            })
            ->leftJoin($branchTable.' as b', 'b.id', '=', 'sc.branch_id')
            ->whereNull('sc.deleted_at')
            ->whereNotNull('sc.branch_id')
            ->whereNull('bc.id')
            ->when(filled($companyId), fn ($query) => $query->where('sc.company_id', (int) $companyId))
            ->select([
                'sc.id as placement_id',
                'sc.company_id',
                'sc.branch_id',
                'b.id as branch_row_id',
                'b.deleted_at as branch_deleted_at',
            ])
            ->orderBy('sc.company_id')
            ->orderBy('sc.branch_id')
            ->get();
    }

    /**
     * @param  Collection<int, array{company_id: int, branch_id: int}>  $links
     */
    private function insertLinks(Collection $links): void
    {
        $table = config('ppuds.table_prefix').'branch_company';
        $now = now();

        DB::transaction(function () use ($table, $links, $now): void {
            foreach ($links as $link) {
                // is_main stays false: the company already has its main branch,
                // and a restored link must not claim that flag.
                DB::table($table)->insert([
                    'company_id' => $link['company_id'],
                    'branch_id' => $link['branch_id'],
                    'is_main' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function report(string $title, Collection $rows): void
    {
        $this->newLine();
        $this->line($title.': <fg=yellow>'.$rows->count().'</> placement(s)');

        if ($rows->isEmpty()) {
            return;
        }

        $this->table(
            ['Placement', 'Company', 'Branch', 'Branch name'],
            $rows->take(20)->map(fn (object $row): array => [
                (string) $row->placement_id,
                (string) $row->company_id,
                (string) $row->branch_id,
                $this->branchName((int) $row->branch_id),
            ])->all()
        );

        if ($rows->count() > 20) {
            $this->line('  … and '.($rows->count() - 20).' more');
        }
    }

    private function branchName(int $branchId): string
    {
        $branch = Branch::withTrashed()->with('translations')->find($branchId);

        return $branch?->name ?: '—';
    }
}

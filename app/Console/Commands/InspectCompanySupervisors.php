<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only report of who supervises which department in a company, straight
 * from the branch_department pivot. It exists to answer one question when a
 * supervisor shows up that nobody assigned: which row carries them, when was
 * it written, and was it inserted or updated afterwards.
 */
class InspectCompanySupervisors extends Command
{
    protected $signature = 'ppuds:inspect-company-supervisors {company : The company ID}';

    protected $description = 'Show every branch/department supervisor row of one company, with its timestamps.';

    public function handle(): int
    {
        $companyId = (int) $this->argument('company');
        $prefix = config('ppuds.table_prefix');

        $company = DB::table($prefix.'companies')
            ->leftJoin($prefix.'company_translations as ct', function ($join) use ($prefix): void {
                $join->on('ct.company_id', '=', $prefix.'companies.id')->where('ct.locale', '=', 'ar');
            })
            ->where($prefix.'companies.id', $companyId)
            ->select($prefix.'companies.id', $prefix.'companies.created_at', 'ct.name')
            ->first();

        if (! $company) {
            $this->error("Company {$companyId} not found.");

            return self::FAILURE;
        }

        $this->info("Company {$company->id}: {$company->name}  (created {$company->created_at})");

        $this->branchLinks($prefix, $companyId, (string) $company->created_at);

        $rows = DB::table($prefix.'branch_department as bd')
            ->join($prefix.'branch_company as bc', 'bc.branch_id', '=', 'bd.branch_id')
            ->join('users as u', 'u.id', '=', 'bd.user_id')
            ->leftJoin('branch_branch_translations as bt', function ($join): void {
                $join->on('bt.branch_id', '=', 'bd.branch_id')->where('bt.locale', '=', 'ar');
            })
            ->leftJoin($prefix.'company_department_translations as dt', function ($join) use ($prefix): void {
                $join->on('dt.department_id', '=', 'bd.company_department_id')->where('dt.locale', '=', 'ar');
            })
            ->where('bc.company_id', $companyId)
            ->orderBy('bd.branch_id')
            ->orderBy('bd.company_department_id')
            ->orderBy('bd.id')
            ->select([
                'bd.id',
                'bd.branch_id',
                'bt.name as branch_name',
                'bd.company_department_id',
                'dt.name as department_name',
                'bd.user_id',
                'u.name as supervisor',
                'bd.created_at',
                'bd.updated_at',
            ])
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No department supervisors are linked to this company.');

            return self::SUCCESS;
        }

        $this->table(
            ['row', 'branch', 'department', 'supervisor', 'created', 'updated', 'note'],
            $rows->map(fn ($row): array => [
                $row->id,
                $row->branch_id.' · '.($row->branch_name ?? '—'),
                $row->company_department_id.' · '.($row->department_name ?? '—'),
                $row->user_id.' · '.$row->supervisor,
                (string) $row->created_at,
                (string) $row->updated_at,
                $this->note($row, $rows, $company->created_at),
            ])->all()
        );

        $duplicates = $rows
            ->groupBy(fn ($row): string => $row->branch_id.'-'.$row->company_department_id)
            ->filter(fn ($group): bool => $group->count() > 1);

        if ($duplicates->isNotEmpty()) {
            $this->warn('Duplicate rows: the same branch+department carries more than one supervisor.');
            $this->line('The company form only holds one, so the extra rows are invisible in the UI.');
        }

        return self::SUCCESS;
    }

    /**
     * The branches attached to the company, and when each attachment was made.
     * A branch older than the company, or one shared with another company, is
     * how a supervisor nobody picked ends up on the company.
     */
    private function branchLinks(string $prefix, int $companyId, string $companyCreatedAt): void
    {
        $links = DB::table($prefix.'branch_company as bc')
            ->join('branch_branches as b', 'b.id', '=', 'bc.branch_id')
            ->leftJoin('branch_branch_translations as bt', function ($join): void {
                $join->on('bt.branch_id', '=', 'b.id')->where('bt.locale', '=', 'ar');
            })
            ->where('bc.company_id', $companyId)
            ->orderBy('bc.branch_id')
            ->select([
                'bc.branch_id',
                'bt.name as branch_name',
                'bc.is_main',
                'bc.created_at as linked_at',
                'b.created_at as branch_created_at',
            ])
            ->get();

        if ($links->isEmpty()) {
            $this->warn('No branches are attached to this company.');

            return;
        }

        $sharedCounts = DB::table($prefix.'branch_company')
            ->whereIn('branch_id', $links->pluck('branch_id'))
            ->select('branch_id', DB::raw('count(*) as companies'))
            ->groupBy('branch_id')
            ->pluck('companies', 'branch_id');

        $this->line('');
        $this->line('Branches attached to this company:');
        $this->table(
            ['branch', 'name', 'main', 'branch created', 'linked to company at', 'shared with', 'note'],
            $links->map(function ($link) use ($sharedCounts, $companyCreatedAt): array {
                $shared = (int) ($sharedCounts[$link->branch_id] ?? 1);
                $notes = [];

                if ($link->branch_created_at && $link->branch_created_at < $companyCreatedAt) {
                    $notes[] = 'PRE-EXISTING BRANCH';
                }

                if ($shared > 1) {
                    $notes[] = 'SHARED WITH '.($shared - 1).' OTHER COMPANY(IES)';
                }

                return [
                    $link->branch_id,
                    $link->branch_name ?? '—',
                    $link->is_main ? 'yes' : 'no',
                    (string) $link->branch_created_at,
                    (string) $link->linked_at,
                    $shared.' company(ies)',
                    implode(' + ', $notes),
                ];
            })->all()
        );
    }

    /** Flags the rows worth explaining: duplicates, later edits, pre-existing rows. */
    private function note(object $row, $rows, ?string $companyCreatedAt): string
    {
        $notes = [];

        $siblings = $rows->filter(
            fn ($other): bool => $other->branch_id === $row->branch_id
                && $other->company_department_id === $row->company_department_id
        );

        if ($siblings->count() > 1) {
            $notes[] = 'DUPLICATE';
        }

        if ($row->updated_at && $row->created_at && $row->updated_at !== $row->created_at) {
            $notes[] = 'OVERWRITTEN';
        }

        if ($companyCreatedAt && $row->created_at && $row->created_at < $companyCreatedAt) {
            $notes[] = 'OLDER THAN COMPANY';
        }

        return implode(' + ', $notes);
    }
}

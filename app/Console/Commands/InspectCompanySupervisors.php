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

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\Branch\Entities\Branch;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;

class FillBranchLocationsFromStudentAttendance extends Command
{
    protected $signature = 'ppuds:fill-branch-locations-from-attendance
        {--company-id=* : Limit to one or more company IDs. Comma separated values are supported.}
        {--branch-id=* : Limit to one or more branch IDs. Comma separated values are supported.}
        {--student-id=* : Use attendance locations from one or more student user IDs only.}
        {--student-company-id=* : Use one or more student-company assignment IDs only.}
        {--limit= : Maximum number of branches to fill.}
        {--force : Overwrite branches that already have coordinates.}
        {--dry-run : Preview changes without saving.}';

    protected $description = 'Fill missing branch coordinates from one student attendance coordinate per branch.';

    public function handle(): int
    {
        $companyIds = $this->integerListOption('company-id');
        $branchIds = $this->integerListOption('branch-id');
        $studentIds = $this->integerListOption('student-id');
        $studentCompanyIds = $this->integerListOption('student-company-id');
        $limit = $this->positiveIntegerOption('limit');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $query = $this->branchesQuery($companyIds, $branchIds, $force);
        $totalBranches = (clone $query)->count();

        if ($totalBranches === 0) {
            $this->info('No branches matched the selected filters.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry run enabled: no branch coordinates will be saved.');
        }

        $stats = [
            'checked' => 0,
            'updated' => 0,
            'matched_dry_run' => 0,
            'skipped_no_attendance_location' => 0,
        ];
        $rows = [];
        $processed = 0;

        $query->orderBy('id')->chunkById(100, function ($branches) use (
            $companyIds,
            $studentIds,
            $studentCompanyIds,
            $limit,
            $dryRun,
            &$stats,
            &$rows,
            &$processed
        ): bool {
            foreach ($branches as $branch) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $processed++;
                $stats['checked']++;

                $attendance = $this->attendanceWithLocationForBranch(
                    $branch,
                    $companyIds,
                    $studentIds,
                    $studentCompanyIds
                );

                if (! $attendance) {
                    $stats['skipped_no_attendance_location']++;

                    continue;
                }

                $coordinates = $this->coordinatesFromAttendance($attendance);

                if ($coordinates === null) {
                    $stats['skipped_no_attendance_location']++;

                    continue;
                }

                [$latitude, $longitude, $source] = $coordinates;
                $studentCompany = $attendance->studentCompany;

                $rows[] = [
                    'branch_id' => $branch->id,
                    'branch' => $this->branchLabel($branch),
                    'company_id' => $studentCompany?->company_id,
                    'company' => $studentCompany?->company?->name,
                    'student_company_id' => $studentCompany?->id,
                    'student' => $studentCompany?->student?->name,
                    'attendance_id' => $attendance->id,
                    'source' => $source,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];

                if ($dryRun) {
                    $stats['matched_dry_run']++;

                    continue;
                }

                $branch->forceFill([
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ])->save();

                $stats['updated']++;
            }

            return true;
        });

        if ($rows !== []) {
            $this->table(
                [
                    'Branch ID',
                    'Branch',
                    'Company ID',
                    'Company',
                    'Student Company ID',
                    'Student',
                    'Attendance ID',
                    'Source',
                    'Latitude',
                    'Longitude',
                ],
                $rows
            );
        }

        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all()
        );

        return self::SUCCESS;
    }

    private function branchesQuery(array $companyIds, array $branchIds, bool $force): Builder
    {
        $companyTable = (new Company())->getTable();

        return Branch::query()
            ->with(['translations'])
            ->when($branchIds !== [], fn (Builder $query) => $query->whereIn('id', $branchIds))
            ->when($companyIds !== [], function (Builder $query) use ($companyIds, $companyTable): void {
                $query->where(function (Builder $query) use ($companyIds, $companyTable): void {
                    $query
                        ->whereHas('companies', function (Builder $query) use ($companyIds, $companyTable): void {
                            $query->whereIn("{$companyTable}.id", $companyIds);
                        })
                        ->orWhereIn(
                            'id',
                            StudentCompany::query()
                                ->select('branch_id')
                                ->whereIn('company_id', $companyIds)
                                ->whereNotNull('branch_id')
                        );
                });
            })
            ->when(! $force, function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereNull('latitude')
                        ->orWhereNull('longitude');
                });
            });
    }

    private function attendanceWithLocationForBranch(
        Branch $branch,
        array $companyIds,
        array $studentIds,
        array $studentCompanyIds
    ): ?StudentAttendance {
        $branchCompanyIds = $this->companyIdsForBranch($branch, $companyIds);

        return StudentAttendance::query()
            ->with(['studentCompany.student', 'studentCompany.company.translations'])
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereNotNull('check_in_latitude')
                        ->whereNotNull('check_in_longitude');
                })->orWhere(function (Builder $query): void {
                    $query->whereNotNull('check_out_latitude')
                        ->whereNotNull('check_out_longitude');
                });
            })
            ->whereHas('studentCompany', function (Builder $query) use (
                $branch,
                $companyIds,
                $branchCompanyIds,
                $studentIds,
                $studentCompanyIds
            ): void {
                $query
                    ->when($companyIds !== [], fn (Builder $query) => $query->whereIn('company_id', $companyIds))
                    ->when($studentIds !== [], fn (Builder $query) => $query->whereIn('student_id', $studentIds))
                    ->when(
                        $studentCompanyIds !== [],
                        fn (Builder $query) => $query->whereIn('id', $studentCompanyIds)
                    )
                    ->where(function (Builder $query) use ($branch, $branchCompanyIds): void {
                        $query->where('branch_id', $branch->id);

                        if ($branchCompanyIds !== []) {
                            $query->orWhere(function (Builder $query) use ($branchCompanyIds): void {
                                $query->whereNull('branch_id')
                                    ->whereIn('company_id', $branchCompanyIds);
                            });
                        }
                    });
            })
            ->orderByDesc('attendance_date')
            ->orderByDesc('check_in')
            ->orderByDesc('check_out')
            ->orderByDesc('id')
            ->first();
    }

    private function companyIdsForBranch(Branch $branch, array $companyIds): array
    {
        $companyTable = (new Company())->getTable();

        $ids = $branch->companies()
            ->pluck("{$companyTable}.id")
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($companyIds === []) {
            return $ids;
        }

        return array_values(array_intersect($ids, $companyIds));
    }

    private function coordinatesFromAttendance(StudentAttendance $attendance): ?array
    {
        if ($this->hasCoordinatePair($attendance->check_in_latitude, $attendance->check_in_longitude)) {
            return [
                $this->formatCoordinate($attendance->check_in_latitude),
                $this->formatCoordinate($attendance->check_in_longitude),
                'check_in',
            ];
        }

        if ($this->hasCoordinatePair($attendance->check_out_latitude, $attendance->check_out_longitude)) {
            return [
                $this->formatCoordinate($attendance->check_out_latitude),
                $this->formatCoordinate($attendance->check_out_longitude),
                'check_out',
            ];
        }

        return null;
    }

    private function hasCoordinatePair(mixed $latitude, mixed $longitude): bool
    {
        return ! blank($latitude) && ! blank($longitude);
    }

    private function formatCoordinate(mixed $coordinate): string
    {
        return number_format((float) $coordinate, 8, '.', '');
    }

    private function branchLabel(Branch $branch): string
    {
        return $branch->name ?: "Branch #{$branch->id}";
    }

    private function integerListOption(string $name): array
    {
        return collect((array) $this->option($name))
            ->flatMap(fn ($value): array => explode(',', (string) $value))
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => $value !== '' && ctype_digit($value))
            ->map(fn (string $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if (! ctype_digit($value) || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }
}

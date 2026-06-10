<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\PPUDS\Services\StudentCompanyPlacementImporter;

class ImportStudentCompanyPlacements extends Command
{
    protected $signature = 'ppuds:import-student-company-placements
        {file : Excel file path}
        {--year= : Academic year. Defaults to PPUDS settings year.}
        {--semester= : Semester number. Defaults to PPUDS settings semester.}
        {--created-by= : User ID used for created_by fallback. Defaults to the first user.}
        {--sheet=* : Import only the given sheet name. Can be repeated.}
        {--include-portal : Include the summary potral sheet. By default it is skipped when detailed sheets exist.}
        {--update-existing : Update existing companies, supervisors, branches, and student-company links.}
        {--use-latest-registration : If no current registration exists, link the latest student registration.}
        {--dry-run : Preview the import and roll back all database writes.}';

    protected $description = 'Import PPUDS student company placements from an Excel file.';

    public function handle(StudentCompanyPlacementImporter $importer): int
    {
        $file = (string) $this->argument('file');

        $this->info('Starting PPUDS placement import...');

        if ($this->option('dry-run')) {
            $this->warn('Dry run enabled: all database writes will be rolled back.');
        }

        $result = $importer->import($file, [
            'year' => $this->option('year') ? (int) $this->option('year') : null,
            'semester' => $this->option('semester') ? (int) $this->option('semester') : null,
            'created_by' => $this->option('created-by') ? (int) $this->option('created-by') : null,
            'sheets' => (array) $this->option('sheet'),
            'include_portal' => (bool) $this->option('include-portal'),
            'update_existing' => (bool) $this->option('update-existing'),
            'use_latest_registration' => (bool) $this->option('use-latest-registration'),
            'dry_run' => (bool) $this->option('dry-run'),
        ]);

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($result['stats'])
                ->map(fn (int $count, string $metric): array => [$metric, $count])
                ->values()
                ->all()
        );

        if ($result['issues'] !== []) {
            $this->newLine();
            $this->warn('Import warnings/errors:');

            foreach (array_slice($result['issues'], 0, 30) as $issue) {
                $this->line(sprintf(
                    '- %s row %s: %s',
                    $issue['sheet'],
                    $issue['row'],
                    $issue['message']
                ));
            }

            if (count($result['issues']) > 30) {
                $this->line('...and '.(count($result['issues']) - 30).' more.');
            }
        }

        $this->newLine();
        $this->info($this->option('dry-run') ? 'Dry run completed.' : 'Placement import completed.');

        return self::SUCCESS;
    }
}

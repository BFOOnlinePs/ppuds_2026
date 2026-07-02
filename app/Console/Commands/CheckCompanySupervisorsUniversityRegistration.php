<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Services\PpuApiService;

class CheckCompanySupervisorsUniversityRegistration extends Command
{
    protected $signature = 'ppuds:check-company-supervisors-university
        {--token= : Bearer token used to call the university API. Defaults to PPU_API_ACCESS_TOKEN.}
        {--company-id=* : Limit the check to one or more company IDs.}
        {--supervisor-id=* : Limit the check to one or more supervisor user IDs.}
        {--password= : Password sent to the university API. Defaults to the supervisor phone.}
        {--chunk=100 : Number of companies to load per chunk.}
        {--limit= : Maximum number of company/supervisor pairs to process.}
        {--dry-run : Preview company supervisors without calling the university API.}';

    protected $description = 'Check/register PPUDS company supervisors in the university API.';

    public function handle(PpuApiService $apiService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $token = $this->tokenOption();

        if (! $dryRun && blank($token)) {
            $this->error('Missing university API token. Pass --token=... or set PPU_API_ACCESS_TOKEN.');

            return self::FAILURE;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $limit = $this->positiveIntegerOption('limit');
        $companyIds = $this->integerListOption('company-id');
        $supervisorIds = $this->integerListOption('supervisor-id');
        $password = $this->option('password') ?: null;

        $stats = [
            'checked' => 0,
            'already_registered' => 0,
            'registered_now' => 0,
            'missing_contact' => 0,
            'failed' => 0,
        ];
        $issues = [];

        if ($dryRun) {
            $this->warn('Dry run enabled: the university API will not be called.');
        }

        $query = Company::query()
            ->with(['branches.supervisors', 'translations'])
            ->whereHas('branches.supervisors')
            ->when(
                $companyIds !== [],
                fn ($query) => $query->whereIn('id', $companyIds)
            );

        $processed = 0;
        $companyCount = (clone $query)->count();
        $bar = $this->output->createProgressBar($companyCount);
        $bar->start();

        $query->chunkById($chunkSize, function (Collection $companies) use (
            $apiService,
            $supervisorIds,
            $password,
            $token,
            $dryRun,
            $limit,
            &$processed,
            &$stats,
            &$issues,
            $bar,
        ): bool {
            foreach ($companies as $company) {
                $supervisors = $company->companySupervisors()
                    ->when(
                        $supervisorIds !== [],
                        fn (Collection $supervisors) => $supervisors->whereIn('id', $supervisorIds)
                    )
                    ->values();

                foreach ($supervisors as $supervisor) {
                    if ($limit !== null && $processed >= $limit) {
                        $bar->advance();

                        return false;
                    }

                    $processed++;

                    $this->checkSupervisor(
                        $apiService,
                        $company,
                        $supervisor,
                        $password,
                        $token,
                        $dryRun,
                        $stats,
                        $issues
                    );
                }

                $bar->advance();
            }

            return true;
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all()
        );

        if ($issues !== []) {
            $this->newLine();
            $this->warn('Issues:');

            foreach (array_slice($issues, 0, 30) as $issue) {
                $this->line(sprintf(
                    '- %s | company #%d %s | supervisor #%d %s',
                    $issue['status'],
                    $issue['company_id'],
                    $issue['company_name'],
                    $issue['supervisor_id'],
                    $issue['supervisor_name']
                ));

                if (filled($issue['message'] ?? null)) {
                    $this->line('  '.$issue['message']);
                }
            }

            if (count($issues) > 30) {
                $this->line('...and '.(count($issues) - 30).' more.');
            }
        }

        if ($dryRun) {
            $this->info('Dry run completed.');

            return self::SUCCESS;
        }

        $this->info('Company supervisor university check completed.');

        return ($stats['failed'] > 0 || $stats['missing_contact'] > 0)
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function checkSupervisor(
        PpuApiService $apiService,
        Company $company,
        User $supervisor,
        ?string $password,
        ?string $token,
        bool $dryRun,
        array &$stats,
        array &$issues,
    ): void {
        $stats['checked']++;

        if (! $this->hasRequiredContact($company, $supervisor)) {
            $stats['missing_contact']++;
            $issues[] = $this->issue($company, $supervisor, 'missing_contact', 'Supervisor or company branch is missing email/mobile.');

            return;
        }

        if ($dryRun) {
            $this->verboseLine($company, $supervisor, 'would_check');

            return;
        }

        $result = $apiService->addCompanyToUniversity(
            $company->fresh(['branches.supervisors', 'translations']) ?? $company,
            $password,
            $supervisor->id,
            $token,
            userId: null,
            sendEvenIfCompanyExists: true,
        );

        if (($result['operation'] ?? null) === 'already_exists') {
            $stats['already_registered']++;
            $this->verboseLine($company, $supervisor, 'already_registered');

            return;
        }

        if (($result['success'] ?? false) === true) {
            $stats['registered_now']++;
            $this->verboseLine($company, $supervisor, 'registered_now');

            return;
        }

        $stats['failed']++;
        $issues[] = $this->issue(
            $company,
            $supervisor,
            'failed',
            $this->failureMessage($result)
        );
    }

    private function hasRequiredContact(Company $company, User $supervisor): bool
    {
        $branch = $company->branches->first();
        $email = filled($supervisor->email) ? strtolower(trim((string) $supervisor->email)) : $branch?->email;
        $phone = $this->normalizePhone($supervisor->phone ?: $branch?->phone);

        return filled($email) && filled($phone);
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        return preg_replace('/\s+/', '', trim((string) $phone));
    }

    private function failureMessage(?array $result): string
    {
        if ($result === null) {
            return 'The university API request failed or returned no usable result.';
        }

        $message = data_get($result, 'response.message')
            ?? data_get($result, 'response.msg')
            ?? data_get($result, 'response.error');

        return is_string($message) && $message !== ''
            ? $message
            : 'The university API rejected the supervisor.';
    }

    private function issue(Company $company, User $supervisor, string $status, string $message): array
    {
        return [
            'status' => $status,
            'company_id' => $company->id,
            'company_name' => (string) ($company->name ?? "Company {$company->id}"),
            'supervisor_id' => $supervisor->id,
            'supervisor_name' => (string) ($supervisor->name ?? "User {$supervisor->id}"),
            'message' => $message,
        ];
    }

    private function verboseLine(Company $company, User $supervisor, string $status): void
    {
        if (! $this->output->isVerbose()) {
            return;
        }

        $this->newLine();
        $this->line(sprintf(
            '%s | company #%d %s | supervisor #%d %s',
            $status,
            $company->id,
            $company->name,
            $supervisor->id,
            $supervisor->name
        ));
    }

    private function tokenOption(): ?string
    {
        $token = $this->option('token') ?: config('services.ppu_api.access_token');

        return filled($token) ? (string) $token : null;
    }

    private function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        if (blank($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function integerListOption(string $name): array
    {
        return collect((array) $this->option($name))
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }
}

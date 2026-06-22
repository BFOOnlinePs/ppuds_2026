<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\PPUDS\Entities\StudentProfile;
use Throwable;

class GenerateStudentAvatars extends Command
{
    protected $signature = 'ppuds:generate-student-avatars
        {--force : Regenerate avatars for students who already have one.}
        {--dry-run : Show what would be generated without writing files.}
        {--chunk=500 : Number of students to process per chunk.}';

    protected $description = 'Generate default avatar images for PPUDS students.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $query = StudentProfile::query()
            ->whereHas('user')
            ->with(['user.media'])
            ->when(! $force, fn (Builder $query) => $query->whereHas(
                'user',
                fn (Builder $userQuery) => $userQuery->whereDoesntHave(
                    'media',
                    fn (Builder $mediaQuery) => $mediaQuery->where('collection_name', 'avatar')
                )
            ));

        $studentsCount = StudentProfile::query()->whereHas('user')->count();
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info($studentsCount === 0 ? 'No students were found.' : 'All students already have avatars.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry run enabled: no avatar files will be written.');
        }

        $stats = [
            'generated' => 0,
            'regenerated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById($chunkSize, function (EloquentCollection $studentProfiles) use ($force, $dryRun, $bar, &$stats): void {
            foreach ($studentProfiles as $studentProfile) {
                $user = $studentProfile->user;
                $hasAvatar = (bool) $user?->getFirstMedia('avatar');

                if (! $force && $hasAvatar) {
                    $stats['skipped']++;
                    $bar->advance();

                    continue;
                }

                try {
                    if (! $dryRun) {
                        $user->generateAvatar();
                    }

                    $hasAvatar ? $stats['regenerated']++ : $stats['generated']++;
                } catch (Throwable $exception) {
                    report($exception);
                    $stats['failed']++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            '%s Generated: %d, Regenerated: %d, Skipped: %d, Failed: %d',
            $dryRun ? 'Preview complete.' : 'Done.',
            $stats['generated'],
            $stats['regenerated'],
            $stats['skipped'],
            $stats['failed'],
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

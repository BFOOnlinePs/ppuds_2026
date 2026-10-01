<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings;

/**
 * رُصدت علامة الطالب على تدريبه المنتهي ثم انتقل لتدريب ساري، فبقي الساري
 * بلا علامة. هذا الأمر ينقل علامتي مشرف التقييم ومشرف الجامعة من التدريب
 * المنتهي إلى التدريب الساري لنفس التسجيل، ولا يغيّر حالة أي تدريب.
 *
 * مشرف الجامعة على التسجيل فهو نفسه للتدريبين. أما مشرف التقييم فمُسند على
 * التدريب، فينتقل مع علامته: لو بقي على المنتهي لظهر عنده طالباً بانتظار الرصد.
 * علامة الشركة محسوبة من الاستبيان وينقلها ppuds:move-finished-company-evaluations.
 *
 * لا تُنقل علامة إذا كان الساري مرصوداً فيها، أو كان له مشرف تقييم آخر.
 * يعرض ما سيُنقل فقط؛ أضف --force للنقل.
 */
class MoveFinishedPlacementGrades extends Command
{
    protected $signature = 'ppuds:move-finished-grades
        {--student-company-id=* : Limit to one or more finished student-company IDs. Comma separated values are supported.}
        {--force : Move the grades. Without it the command only reports what it would move.}';

    protected $description = 'Move evaluation and university supervisor grades from finished trainings to the active training of the same registration.';

    /** عمود العلامة => عنوانها في التقرير. */
    private const GRADES = [
        'evaluation_score' => 'Evaluation supervisor',
        'supervisor_score' => 'University supervisor',
    ];

    public function handle(): int
    {
        $sources = $this->gradedFinishedPlacements($this->integerListOption('student-company-id'));

        if ($sources->isEmpty()) {
            $this->info('No grades are recorded on finished trainings of the current semester.');

            return self::SUCCESS;
        }

        [$movable, $skipped] = $this->planMoves($sources)
            ->partition(fn (array $move): bool => $move['reason'] === null);

        $this->report('Grades that can be moved', $movable);
        $this->report('Grades left on the finished training', $skipped);

        if ($movable->isEmpty()) {
            $this->newLine();
            $this->warn('Nothing to move.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('Preview only — '.$movable->count().' grade(s) would be moved. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        foreach ($movable->groupBy(fn (array $move): int => $move['source']->id) as $moves) {
            DB::transaction(function () use ($moves): void {
                $source = $moves->first()['source'];
                $target = $moves->first()['target'];
                $targetChanges = [];
                $sourceChanges = [];

                foreach ($moves as $move) {
                    $targetChanges[$move['column']] = $source->{$move['column']};
                    $sourceChanges[$move['column']] = null;

                    if ($move['column'] === 'evaluation_score') {
                        $targetChanges['evaluation_supervisor_id'] = $target->evaluation_supervisor_id ?? $source->evaluation_supervisor_id;
                        $sourceChanges['evaluation_supervisor_id'] = null;
                    }
                }

                // عبر النموذج ليُسجَّل النقل في سجل النشاط كأي رصد للعلامة.
                $target->update($targetChanges);
                $source->update($sourceChanges);
            });
        }

        $this->newLine();
        $this->info('Moved '.$movable->count().' grade(s).');

        return self::SUCCESS;
    }

    private function gradedFinishedPlacements(array $studentCompanyIds): Collection
    {
        $settings = app(GeneralSettings::class);

        return StudentCompany::query()
            ->with(['student', 'company.translations', 'evaluationSupervisor', 'registration.supervisor'])
            ->where('status', TrainingStatus::FINISHED->value)
            ->where(fn (Builder $query): Builder => $query
                ->whereNotNull('evaluation_score')
                ->orWhereNotNull('supervisor_score'))
            ->whereHas('registration', fn (Builder $query): Builder => $query
                ->where('year', $settings->year)
                ->where('semester', $settings->semester_type->value))
            ->when(
                $studentCompanyIds !== [],
                fn (Builder $query): Builder => $query->whereKey($studentCompanyIds)
            )
            ->get();
    }

    /**
     * علامة واحدة لكل (تدريب منتهٍ + نوع علامة)، مع التدريب الساري الذي
     * ستُنقل إليه وسبب تركها إن وُجد.
     *
     * @param  Collection<int, StudentCompany>  $sources
     * @return Collection<int, array<string, mixed>>
     */
    private function planMoves(Collection $sources): Collection
    {
        // التدريب الساري للتسجيل حتى لو كان أقدم من المنتهي.
        $targets = StudentCompany::query()
            ->whereIn('registration_id', $sources->pluck('registration_id')->unique()->values())
            ->where('status', TrainingStatus::AVAILABLE->value)
            ->currentPerRegistration()
            ->get()
            ->keyBy('registration_id');

        return collect(array_keys(self::GRADES))
            ->flatMap(function (string $column) use ($sources, $targets): Collection {
                $graded = $sources->whereNotNull($column);

                // العلامة نفسها مرصودة على أكثر من تدريب منتهٍ للتسجيل: لا يُعرف أيّها المقصود.
                $gradedPlacementsPerRegistration = $graded->countBy('registration_id');

                return $graded->map(function (StudentCompany $source) use ($column, $targets, $gradedPlacementsPerRegistration): array {
                    $target = $targets->get($source->registration_id);

                    return [
                        'source' => $source,
                        'target' => $target,
                        'column' => $column,
                        'reason' => $this->skipReason(
                            $source,
                            $target,
                            $column,
                            $gradedPlacementsPerRegistration[$source->registration_id]
                        ),
                    ];
                });
            })
            ->values();
    }

    private function skipReason(StudentCompany $source, ?StudentCompany $target, string $column, int $gradedPlacements): ?string
    {
        if (! $target) {
            return 'No active training for this registration';
        }

        if ($gradedPlacements > 1) {
            return 'Graded on more than one finished training';
        }

        if ($target->{$column} !== null) {
            return 'Active training is already graded';
        }

        if ($column === 'evaluation_score'
            && $source->evaluation_supervisor_id !== null
            && $target->evaluation_supervisor_id !== null
            && (int) $source->evaluation_supervisor_id !== (int) $target->evaluation_supervisor_id) {
            return 'Active training has a different evaluation supervisor';
        }

        return null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $moves
     */
    private function report(string $title, Collection $moves): void
    {
        $this->newLine();
        $this->line($title.': <fg=yellow>'.$moves->count().'</> grade(s)');

        if ($moves->isEmpty()) {
            return;
        }

        $this->table(
            ['Finished', 'Active', 'Student', 'Company', 'Grade', 'Score', 'Active score', 'Graded by', 'Reason'],
            $moves->map(fn (array $move): array => [
                (string) $move['source']->id,
                $move['target'] ? (string) $move['target']->id : '—',
                $move['source']->student?->name ?: '—',
                $move['source']->company?->name ?: '—',
                self::GRADES[$move['column']],
                (string) $move['source']->{$move['column']},
                (string) ($move['target']?->{$move['column']} ?? '—'),
                ($move['column'] === 'evaluation_score'
                    ? $move['source']->evaluationSupervisor?->name
                    : $move['source']->registration?->supervisor?->name) ?: '—',
                $move['reason'] ?? '',
            ])->all()
        );
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
}

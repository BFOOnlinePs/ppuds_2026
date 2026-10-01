<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Services\CompanyEvaluationGradeService;
use Modules\PPUDS\Settings\GeneralSettings;

/**
 * كان مشرف الشركة يرى التدريب المنتهي بجانب التدريب الساري للطالب نفسه، فقيّم
 * المنتهي. بعد إخفاء المنتهي بقي تقييمه عليه وظهر التدريب الساري بلا علامة.
 *
 * هذا الأمر ينقل إجابات استبيان مشرف الشركة من التدريب المنتهي إلى التدريب
 * الساري لنفس التسجيل، ثم يعيد حساب علامة الشركة على الاثنين. علامة الشركة
 * محسوبة من الإجابات، فنقل الرقم وحده كان سيضيع عند أول إعادة حساب.
 *
 * لا يُنقل التقييم إلا إذا كان من قيّم هو مشرف التدريب الساري، ولم يُقيَّم
 * الساري في الاستبيان نفسه. يعرض ما سيُنقل فقط؛ أضف --force للنقل.
 */
class MoveFinishedPlacementCompanyEvaluations extends Command
{
    protected $signature = 'ppuds:move-finished-company-evaluations
        {--student-company-id=* : Limit to one or more finished student-company IDs. Comma separated values are supported.}
        {--force : Move the evaluations. Without it the command only reports what it would move.}';

    protected $description = 'Move company supervisor evaluations from finished trainings to the active training of the same registration.';

    public function handle(CompanyEvaluationGradeService $grades): int
    {
        $answers = $this->finishedPlacementAnswers($this->integerListOption('student-company-id'));

        if ($answers->isEmpty()) {
            $this->info('No company supervisor evaluations are recorded on finished trainings of the current semester.');

            return self::SUCCESS;
        }

        [$movable, $skipped] = $this->planMoves($answers)
            ->partition(fn (array $move): bool => $move['reason'] === null);

        $this->report('Evaluations that can be moved', $movable);
        $this->report('Evaluations left on the finished training', $skipped);

        if ($movable->isEmpty()) {
            $this->newLine();
            $this->warn('Nothing to move.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('Preview only — '.$movable->count().' evaluation(s) would be moved. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        foreach ($movable as $move) {
            DB::transaction(function () use ($move, $grades): void {
                // عبر النموذج ليُسجَّل النقل في سجل النشاط كأي تعديل على الإجابة.
                SurveyAnswer::query()
                    ->whereKey($move['answer_ids'])
                    ->get()
                    ->each(fn (SurveyAnswer $answer) => $answer->update(['student_company_id' => $move['target']->id]));

                $grades->refreshFor($move['target']);
                $grades->refreshFor($move['source']);
            });
        }

        $this->newLine();
        $this->info('Moved '.$movable->count().' evaluation(s).');

        return self::SUCCESS;
    }

    /**
     * إجابات استبيانات مشرف الشركة المسجلة على تدريبات منتهية في الفصل الحالي،
     * وهو الفصل الذي يعرض الاستبيان تدريباته للتقييم.
     */
    private function finishedPlacementAnswers(array $studentCompanyIds): Collection
    {
        $settings = app(GeneralSettings::class);

        return SurveyAnswer::query()
            ->with(['survey.translations', 'submittedBy'])
            ->whereHas('survey', fn (Builder $query): Builder => $query->where('serve_group', UserRole::COMPANY_SUPERVISOR->value))
            ->whereHas('studentCompany', fn (Builder $query): Builder => $query
                ->where('status', TrainingStatus::FINISHED->value)
                ->whereHas('registration', fn (Builder $registrationQuery): Builder => $registrationQuery
                    ->where('year', $settings->year)
                    ->where('semester', $settings->semester_type->value)))
            ->when(
                $studentCompanyIds !== [],
                fn (Builder $query): Builder => $query->whereIn('student_company_id', $studentCompanyIds)
            )
            ->get(['id', 'survey_id', 'submitted_by', 'student_company_id']);
    }

    /**
     * تقييم واحد لكل (تدريب منتهٍ + استبيان + مقيّم)، مع التدريب الساري الذي
     * سيُنقل إليه وسبب تركه إن وُجد.
     *
     * @param  Collection<int, SurveyAnswer>  $answers
     * @return Collection<int, array<string, mixed>>
     */
    private function planMoves(Collection $answers): Collection
    {
        $sources = StudentCompany::query()
            ->with(['student', 'company.translations'])
            ->whereKey($answers->pluck('student_company_id')->unique()->values())
            ->get()
            ->keyBy('id');

        // آخر تدريب للتسجيل وحده، وبشرط أن يكون سارياً.
        $targets = StudentCompany::query()
            ->whereIn('registration_id', $sources->pluck('registration_id')->unique()->values())
            ->where('status', TrainingStatus::AVAILABLE->value)
            ->latestPerRegistration()
            ->get()
            ->keyBy('registration_id');

        // الاستبيان نفسه مُجاب على أكثر من تدريب منتهٍ للتسجيل: لا يُعرف أيّها المقصود.
        $finishedPlacementsPerSurvey = $answers
            ->groupBy(fn (SurveyAnswer $answer): string => $sources[$answer->student_company_id]->registration_id.'-'.$answer->survey_id)
            ->map(fn (Collection $group): int => $group->pluck('student_company_id')->unique()->count());

        return $answers
            ->groupBy(fn (SurveyAnswer $answer): string => $answer->student_company_id.'-'.$answer->survey_id.'-'.$answer->submitted_by)
            ->map(function (Collection $group) use ($sources, $targets, $finishedPlacementsPerSurvey): array {
                $answer = $group->first();
                $source = $sources[$answer->student_company_id];
                $target = $targets->get($source->registration_id);

                return [
                    'source' => $source,
                    'target' => $target,
                    'survey' => $answer->survey,
                    'supervisor' => $answer->submittedBy,
                    'answer_ids' => $group->pluck('id')->all(),
                    'reason' => $this->skipReason(
                        $answer,
                        $target,
                        $finishedPlacementsPerSurvey[$source->registration_id.'-'.$answer->survey_id]
                    ),
                ];
            })
            ->values();
    }

    private function skipReason(SurveyAnswer $answer, ?StudentCompany $target, int $finishedPlacementsInSurvey): ?string
    {
        if (! $target) {
            return 'No active training for this registration';
        }

        if ($finishedPlacementsInSurvey > 1) {
            return 'Evaluated on more than one finished training';
        }

        if ($target->surveyAnswers()->where('survey_id', $answer->survey_id)->exists()) {
            return 'Active training is already evaluated in this survey';
        }

        if (! $this->supervisesPlacement((int) $answer->submitted_by, $target)) {
            return 'The evaluator does not supervise the active training';
        }

        return null;
    }

    /**
     * مقعد المقيّم في فرع وقسم التدريب الساري، كما يحدد الاستبيان من يقيّم مَن.
     */
    private function supervisesPlacement(int $userId, StudentCompany $placement): bool
    {
        return DB::table(config('ppuds.table_prefix').'branch_department')
            ->where('branch_id', $placement->branch_id)
            ->where('company_department_id', $placement->department_id)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $moves
     */
    private function report(string $title, Collection $moves): void
    {
        $this->newLine();
        $this->line($title.': <fg=yellow>'.$moves->count().'</> evaluation(s)');

        if ($moves->isEmpty()) {
            return;
        }

        $this->table(
            ['Finished', 'Active', 'Student', 'Company', 'Survey', 'Supervisor', 'Answers', 'Reason'],
            $moves->map(fn (array $move): array => [
                (string) $move['source']->id,
                $move['target'] ? (string) $move['target']->id : '—',
                $move['source']->student?->name ?: '—',
                $move['source']->company?->name ?: '—',
                $move['survey']?->title ?: '—',
                $move['supervisor']?->name ?: '—',
                (string) count($move['answer_ids']),
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

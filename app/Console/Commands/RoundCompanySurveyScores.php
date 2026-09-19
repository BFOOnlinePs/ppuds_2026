<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\PPUDS\Entities\StudentCompany;

/**
 * علامة الشركة صارت تُحسب مقرَّبة لأقرب عدد صحيح، لكن العلامات المرصودة قبل
 * ذلك بقيت بكسورها. هذا الأمر يقرّبها دفعة واحدة دون إعادة حسابها من
 * الاستبيان، فالقيمة المخزَّنة هي نفسها المقصودة وينقصها التقريب فقط.
 */
class RoundCompanySurveyScores extends Command
{
    protected $signature = 'ppuds:round-company-survey-scores
        {--student-company-id=* : Limit to one or more student-company assignment IDs. Comma separated values are supported.}
        {--limit= : Maximum number of records to round.}
        {--dry-run : Preview changes without saving.}';

    protected $description = 'Round existing company survey scores to the nearest whole number.';

    public function handle(): int
    {
        $studentCompanyIds = $this->integerListOption('student-company-id');
        $limit = $this->positiveIntegerOption('limit');
        $dryRun = (bool) $this->option('dry-run');

        $query = $this->scoresQuery($studentCompanyIds);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No company survey scores need rounding.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry run enabled: no scores will be saved.');
        }

        $stats = [
            'checked' => 0,
            'updated' => 0,
            'matched_dry_run' => 0,
        ];
        $rows = [];
        $processed = 0;

        $query->orderBy('id')->chunkById(200, function ($studentCompanies) use (
            $limit,
            $dryRun,
            &$stats,
            &$rows,
            &$processed
        ): bool {
            foreach ($studentCompanies as $studentCompany) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $processed++;
                $stats['checked']++;

                $current = (float) $studentCompany->company_survey_score;
                $rounded = round($current);

                $rows[] = [
                    'student_company_id' => $studentCompany->id,
                    'student' => $studentCompany->student?->name,
                    'company' => $studentCompany->company?->name,
                    'before' => $current,
                    'after' => $rounded,
                ];

                if ($dryRun) {
                    $stats['matched_dry_run']++;

                    continue;
                }

                // نفس مسار الكتابة المستخدم في CompanyEvaluationGradeService حتى
                // يبقى سجل النشاط متسقاً مع أي رصد علامة آخر.
                $studentCompany->update(['company_survey_score' => $rounded]);

                $stats['updated']++;
            }

            return true;
        });

        if ($rows !== []) {
            $this->table(
                ['Student Company ID', 'Student', 'Company', 'Before', 'After'],
                $rows
            );
        }

        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all()
        );

        return self::SUCCESS;
    }

    /**
     * الصفوف ذات الكسور وحدها: المقرَّبة أصلاً لا داعي لكتابتها من جديد ولا
     * لإضافة سجل نشاط لها.
     */
    private function scoresQuery(array $studentCompanyIds): Builder
    {
        return StudentCompany::query()
            ->with(['student', 'company.translations'])
            ->whereNotNull('company_survey_score')
            ->whereRaw('company_survey_score <> ROUND(company_survey_score)')
            ->when(
                $studentCompanyIds !== [],
                fn (Builder $query) => $query->whereIn('id', $studentCompanyIds)
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

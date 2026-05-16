<?php

namespace Modules\Core\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Modules\Core\Ai\StudentCompanySuggestionAgent;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Enums\CompanyStatus;
use Throwable;

class StudentCompanySuggestionService
{
    public function suggest(User $student, Registration $registration, int $limit = 5): array
    {
        $candidates = $this->candidateCompanies();

        if ($candidates->isEmpty()) {
            return [
                'message' => 'لم أجد شركات متاحة للاقتراح حاليًا.',
                'suggestions' => [],
                'used_ai' => false,
            ];
        }

        if (! $this->aiIsConfigured()) {
            return [
                'message' => 'لم يتم ضبط مزود الذكاء الاصطناعي بعد، لذلك عرضت أفضل اقتراحات محلية من بيانات النظام.',
                'suggestions' => $this->fallbackSuggestions($student, $registration, $candidates, $limit),
                'used_ai' => false,
            ];
        }

        try {
            $response = $this->aiSuggestions($student, $registration, $candidates, $limit);
            $suggestions = $this->normalizeSuggestions($response['suggestions'] ?? [], $candidates, $limit);

            if ($suggestions !== []) {
                return [
                    'message' => $response['summary'] ?? 'هذه أفضل الشركات المقترحة حسب بيانات الطالب والشركات المتاحة.',
                    'suggestions' => $suggestions,
                    'used_ai' => true,
                ];
            }
        } catch (Throwable $exception) {
            Log::warning('Student company AI suggestions failed.', [
                'student_id' => $student->id,
                'registration_id' => $registration->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return [
            'message' => 'تعذر توليد اقتراحات الذكاء الاصطناعي الآن، لذلك عرضت أفضل اقتراحات محلية من بيانات النظام.',
            'suggestions' => $this->fallbackSuggestions($student, $registration, $candidates, $limit),
            'used_ai' => false,
        ];
    }

    private function aiSuggestions(User $student, Registration $registration, Collection $candidates, int $limit): array
    {
        $response = StudentCompanySuggestionAgent::make(limit: $limit)->prompt(
            $this->prompt($student, $registration, $candidates, $limit),
            provider: $this->configuredProvider(),
            model: $this->configuredModel(),
            timeout: $this->configuredTimeout(),
        );

        return method_exists($response, 'toArray') ? $response->toArray() : [];
    }

    private function prompt(User $student, Registration $registration, Collection $candidates, int $limit): string
    {
        return json_encode([
            'task' => "اقترح حتى {$limit} شركات مناسبة، وأعد النتيجة حسب schema فقط.",
            'student' => $this->studentPayload($student, $registration),
            'candidate_companies' => $candidates->values()->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function studentPayload(User $student, Registration $registration): array
    {
        $student->loadMissing('studentProfile.major');
        $registration->loadMissing('course');

        return [
            'id' => $student->id,
            'name' => $student->name,
            'student_number' => $student->studentProfile?->student_number,
            'major' => $student->studentProfile?->major?->name,
            'course' => $registration->course?->name,
            'semester' => $registration->semester?->getLabel() ?? $registration->semester?->value,
            'year' => $registration->year,
            'grade' => $registration->grade,
        ];
    }

    private function candidateCompanies(): Collection
    {
        $baseQuery = Company::query()
            ->with(['category', 'branches.departments'])
            ->withCount('currentStudentCompanies')
            ->orderBy('current_student_companies_count')
            ->orderBy('id');

        $companies = (clone $baseQuery)
            ->where('status', CompanyStatus::ACTIVE->value)
            ->limit(40)
            ->get();

        if ($companies->isEmpty()) {
            $companies = $baseQuery->limit(40)->get();
        }

        return $companies
            ->map(fn (Company $company) => $this->companyPayload($company))
            ->values();
    }

    private function companyPayload(Company $company): array
    {
        return [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'category' => $company->category?->name,
            'description' => Str::limit(strip_tags((string) $company->description), 220),
            'current_students_count' => (int) ($company->current_student_companies_count ?? 0),
            'branches' => $company->branches
                ->map(fn ($branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'departments' => $branch->departments
                        ->map(fn ($department) => [
                            'id' => $department->id,
                            'name' => $department->name,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function normalizeSuggestions(array $suggestions, Collection $candidates, int $limit): array
    {
        $candidatesById = $candidates->keyBy('company_id');

        return collect($suggestions)
            ->map(function (array $suggestion) use ($candidatesById) {
                $companyId = (int) ($suggestion['company_id'] ?? 0);
                $candidate = $candidatesById->get($companyId);

                if (! $candidate) {
                    return null;
                }

                [$branchId, $branchName, $departmentId, $departmentName] = $this->resolvePlacement(
                    $candidate,
                    filled($suggestion['branch_id'] ?? null) ? (int) $suggestion['branch_id'] : null,
                    filled($suggestion['department_id'] ?? null) ? (int) $suggestion['department_id'] : null,
                );

                return [
                    'company_id' => $companyId,
                    'company_name' => $candidate['company_name'],
                    'branch_id' => $branchId,
                    'branch_name' => $branchName,
                    'department_id' => $departmentId,
                    'department_name' => $departmentName,
                    'reason' => Str::limit(trim((string) ($suggestion['reason'] ?? '')) ?: 'اقتراح مناسب حسب بيانات الطالب والشركة.', 280),
                    'fit_score' => min(100, max(1, (int) ($suggestion['fit_score'] ?? 70))),
                    'current_students_count' => $candidate['current_students_count'],
                ];
            })
            ->filter()
            ->unique('company_id')
            ->sortByDesc('fit_score')
            ->take($limit)
            ->values()
            ->all();
    }

    private function fallbackSuggestions(User $student, Registration $registration, Collection $candidates, int $limit): array
    {
        $studentText = collect($this->studentPayload($student, $registration))
            ->filter()
            ->implode(' ');

        $keywords = $this->keywords($studentText);

        $suggestions = $candidates
            ->map(function (array $candidate) use ($keywords) {
                $searchable = Str::lower(implode(' ', [
                    $candidate['company_name'],
                    $candidate['category'],
                    $candidate['description'],
                ]));

                $matchedKeywords = collect($keywords)
                    ->filter(fn (string $keyword) => str_contains($searchable, $keyword))
                    ->count();

                $loadPenalty = min(20, $candidate['current_students_count'] * 2);
                $score = min(100, max(45, 62 + ($matchedKeywords * 6) - $loadPenalty));
                [$branchId, $branchName, $departmentId, $departmentName] = $this->resolvePlacement($candidate);

                return [
                    'company_id' => $candidate['company_id'],
                    'company_name' => $candidate['company_name'],
                    'branch_id' => $branchId,
                    'branch_name' => $branchName,
                    'department_id' => $departmentId,
                    'department_name' => $departmentName,
                    'reason' => $matchedKeywords > 0
                        ? 'يوجد تقاطع بين بيانات الطالب ومجال الشركة، مع حمل تدريبي مناسب.'
                        : 'الشركة متاحة حاليًا وعدد الطلاب الحاليين فيها مناسب مقارنة ببقية الشركات.',
                    'fit_score' => $score,
                    'current_students_count' => $candidate['current_students_count'],
                ];
            })
            ->sortBy([
                ['fit_score', 'desc'],
                ['current_students_count', 'asc'],
                ['company_name', 'asc'],
            ])
            ->take($limit)
            ->values()
            ->all();

        return $suggestions;
    }

    private function resolvePlacement(array $candidate, ?int $branchId = null, ?int $departmentId = null): array
    {
        $branches = collect($candidate['branches'] ?? []);
        $branch = $branchId
            ? $branches->firstWhere('id', $branchId)
            : null;

        $branch ??= $branches->first();
        $departments = collect($branch['departments'] ?? []);
        $department = $departmentId
            ? $departments->firstWhere('id', $departmentId)
            : null;

        $department ??= $departments->first();

        return [
            $branch['id'] ?? null,
            $branch['name'] ?? null,
            $department['id'] ?? null,
            $department['name'] ?? null,
        ];
    }

    private function keywords(string $text): array
    {
        return collect(preg_split('/[\s,،؛;:\/|_\-.]+/u', Str::lower($text)) ?: [])
            ->map(fn (string $word) => trim($word))
            ->filter(fn (string $word) => mb_strlen($word) >= 3)
            ->unique()
            ->values()
            ->all();
    }

    private function aiIsConfigured(): bool
    {
        $provider = $this->configuredProvider() ?? config('ai.default');

        if ($provider instanceof Lab) {
            $provider = $provider->value;
        }

        if (is_array($provider)) {
            return collect($provider)
                ->map(fn ($model, $providerName) => is_int($providerName) ? $model : $providerName)
                ->contains(fn ($providerName) => $this->providerHasCredentials((string) $providerName));
        }

        return $this->providerHasCredentials((string) $provider);
    }

    private function providerHasCredentials(string $provider): bool
    {
        if ($provider === 'ollama') {
            return filled(config('ai.providers.ollama.url'));
        }

        return filled(config("ai.providers.{$provider}.key"));
    }

    private function configuredProvider(): Lab|array|string|null
    {
        $provider = config('ai.student_company_suggestions.provider');

        return blank($provider) ? null : $provider;
    }

    private function configuredModel(): ?string
    {
        $model = config('ai.student_company_suggestions.model');

        return blank($model) ? null : $model;
    }

    private function configuredTimeout(): int
    {
        return (int) config('ai.student_company_suggestions.timeout', 60);
    }
}

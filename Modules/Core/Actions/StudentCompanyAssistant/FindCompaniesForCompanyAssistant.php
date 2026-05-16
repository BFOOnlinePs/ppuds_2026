<?php

namespace Modules\Core\Actions\StudentCompanyAssistant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Enums\CompanyStatus;

class FindCompaniesForCompanyAssistant
{
    public function handle(string $message, int $limit = 6): Collection
    {
        $term = $this->searchTerm($message);

        if (blank($term)) {
            return collect();
        }

        $companies = $this->query($term, true)
            ->limit($limit)
            ->get();

        if ($companies->isEmpty()) {
            $companies = $this->query($term, false)
                ->limit($limit)
                ->get();
        }

        return $companies;
    }

    public function toSuggestion(Company $company, ?string $reason = null): array
    {
        $branch = $company->branches->first();
        $department = $branch?->departments?->first();

        return [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'branch_id' => $branch?->id,
            'branch_name' => $branch?->name,
            'department_id' => $department?->id,
            'department_name' => $department?->name,
            'reason' => $reason ?? 'تم اختيار الشركة من طلب المستخدم داخل الشات.',
            'fit_score' => 100,
            'current_students_count' => (int) ($company->current_student_companies_count ?? 0),
        ];
    }

    public function find(int $companyId): ?Company
    {
        return Company::query()
            ->with(['category', 'branches.departments'])
            ->withCount('currentStudentCompanies')
            ->find($companyId);
    }

    public function payload(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'category' => $company->category?->name,
            'branch' => $company->branches->first()?->name,
            'current_students_count' => (int) ($company->current_student_companies_count ?? 0),
        ];
    }

    private function query(string $term, bool $activeOnly): Builder
    {
        return Company::query()
            ->with(['category', 'branches.departments'])
            ->withCount('currentStudentCompanies')
            ->when($activeOnly, fn (Builder $query) => $query->where('status', CompanyStatus::ACTIVE->value))
            ->where(function (Builder $query) use ($term) {
                $query
                    ->where('website', 'like', "%{$term}%")
                    ->orWhereHas('translations', fn (Builder $translationQuery) => $translationQuery->where('name', 'like', "%{$term}%"));
            })
            ->orderBy('current_student_companies_count')
            ->orderBy('id');
    }

    private function searchTerm(string $message): string
    {
        $stopWords = [
            'اربطه',
            'اربطها',
            'اربط',
            'ربط',
            'سجله',
            'سجلها',
            'سجل',
            'مع',
            'في',
            'على',
            'لدى',
            'الى',
            'إلى',
            'الطالب',
            'الطالبة',
            'شركة',
            'الشركة',
        ];

        $term = Str::lower($message);
        $term = preg_replace('/[^\p{Arabic}\p{L}\p{N}\s]+/u', ' ', $term) ?? $term;
        $term = preg_replace('/\s+/u', ' ', $term) ?? $term;

        return collect(explode(' ', trim($term)))
            ->reject(fn (string $word) => in_array($word, $stopWords, true))
            ->implode(' ');
    }
}

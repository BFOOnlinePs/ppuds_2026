<?php

namespace Modules\Core\Ai\Tools\AiAssistant;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Modules\Core\Actions\StudentCompanyAssistant\FindCompaniesForCompanyAssistant;
use Modules\PPUDS\Entities\Company;
use Stringable;

class SearchCompanies extends AiAssistantTool
{
    public function description(): Stringable|string
    {
        return 'البحث عن شركة تدريب بالاسم أو الموقع الإلكتروني. يُرجع company_id وفروعها وأقسامها.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->userCan('Company View List') && ! $this->userCan('StudentCompany Create')) {
            return $this->denied('Company View List');
        }

        $query = trim((string) $request->string('query'));

        if (mb_strlen($query) < 2) {
            return $this->error('اكتب حرفين على الأقل للبحث.');
        }

        $companies = app(FindCompaniesForCompanyAssistant::class)->handle($query, 8);
        $companies->loadMissing('branches.city');

        return $this->respond([
            'ok' => true,
            'count' => $companies->count(),
            'companies' => $companies->map(fn (Company $company) => [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'category' => $company->category?->name,
                'status' => $company->status?->getLabel() ?? $company->status,
                'current_students_count' => (int) ($company->current_student_companies_count ?? 0),
                'branches' => $company->branches->map(fn ($branch) => [
                    'branch_id' => $branch->id,
                    'name' => $branch->name,
                    'city' => $branch->city?->name,
                    'departments' => $branch->departments
                        ->map(fn ($department) => ['department_id' => $department->id, 'name' => $department->name])
                        ->values()
                        ->all(),
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('اسم الشركة أو جزء منه')->required(),
        ];
    }
}

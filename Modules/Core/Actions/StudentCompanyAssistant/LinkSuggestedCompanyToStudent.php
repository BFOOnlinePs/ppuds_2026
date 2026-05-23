<?php

namespace Modules\Core\Actions\StudentCompanyAssistant;

use Illuminate\Support\Facades\DB;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\TrainingStatus;

class LinkSuggestedCompanyToStudent
{
    public function __construct(
        private readonly ResolveCompanyPlacement $resolveCompanyPlacement,
    ) {}

    public function handle(int $studentId, int $registrationId, array $suggestion, int $createdBy): string
    {
        return DB::transaction(function () use ($studentId, $registrationId, $suggestion, $createdBy) {
            $companyId = (int) $suggestion['company_id'];

            $studentCompany = StudentCompany::withTrashed()
                ->where('registration_id', $registrationId)
                ->where('company_id', $companyId)
                ->first();

            if ($studentCompany) {
                return 'already_exists';
            }

            [$branchId, $departmentId] = $this->resolveCompanyPlacement->handle(
                $companyId,
                $suggestion['branch_id'] ?? null,
                $suggestion['department_id'] ?? null,
            );

            $attributes = [
                'student_id' => $studentId,
                'branch_id' => $branchId,
                'department_id' => $departmentId,
                'status' => TrainingStatus::AVAILABLE,
                'created_by' => $createdBy,
            ];

            StudentCompany::create($attributes + [
                'registration_id' => $registrationId,
                'company_id' => $companyId,
            ]);

            return 'created';
        });
    }
}

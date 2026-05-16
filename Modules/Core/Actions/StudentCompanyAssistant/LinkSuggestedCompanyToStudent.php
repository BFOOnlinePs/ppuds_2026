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
            [$branchId, $departmentId] = $this->resolveCompanyPlacement->handle(
                (int) $suggestion['company_id'],
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

            $studentCompany = StudentCompany::withTrashed()
                ->where('registration_id', $registrationId)
                ->where('company_id', $suggestion['company_id'])
                ->first();

            if ($studentCompany) {
                if ($studentCompany->trashed()) {
                    $studentCompany->restore();
                }

                $studentCompany->fill($attributes)->save();

                return 'updated';
            }

            StudentCompany::create($attributes + [
                'registration_id' => $registrationId,
                'company_id' => $suggestion['company_id'],
            ]);

            return 'created';
        });
    }
}

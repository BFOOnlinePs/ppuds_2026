<?php

namespace Modules\PPUDS\Transformers\V1\StudentCompanyAssistant;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentCompanyAssistantCompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'website' => $this->website,
            'category' => $this->category?->name,
            'status' => $this->status instanceof BackedEnum ? $this->status->value : $this->status,
            'current_students_count' => (int) ($this->current_student_companies_count ?? 0),
            'branches' => $this->branches
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
}

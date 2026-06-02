<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurveyEvaluationStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'student_company_id' => $this->id,
            'student_id' => $this->student_id,
            'student_name' => $this->student?->name,
            'student_number' => $this->student?->studentProfile?->student_number,
            'major' => $this->student?->studentProfile?->major?->name,
            'company_id' => $this->company_id,
            'company_name' => $this->company?->name,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,
            'is_evaluated' => (bool) ($this->is_evaluated ?? false),
        ];
    }
}

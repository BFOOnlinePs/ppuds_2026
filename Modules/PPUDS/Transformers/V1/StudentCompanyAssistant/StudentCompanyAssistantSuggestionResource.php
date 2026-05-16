<?php

namespace Modules\PPUDS\Transformers\V1\StudentCompanyAssistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentCompanyAssistantSuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'company_id' => data_get($this->resource, 'company_id'),
            'company_name' => data_get($this->resource, 'company_name'),
            'branch_id' => data_get($this->resource, 'branch_id'),
            'branch_name' => data_get($this->resource, 'branch_name'),
            'department_id' => data_get($this->resource, 'department_id'),
            'department_name' => data_get($this->resource, 'department_name'),
            'reason' => data_get($this->resource, 'reason'),
            'fit_score' => data_get($this->resource, 'fit_score'),
            'current_students_count' => data_get($this->resource, 'current_students_count'),
        ];
    }
}

<?php

namespace Modules\PPUDS\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'semester_type'         => $this->semester_type?->value,
            'year'                  => $this->year,
            'report_status'         => $this->report_status?->value,
            'login_method'          => $this->login_method?->value,
            'giz_evaluation_status' => $this->giz_evaluation_status?->value,
            'start_semester'        => $this->start_semester?->format('Y-m-d'),
            'end_semester'          => $this->end_semester?->format('Y-m-d'),
        ];
    }
}

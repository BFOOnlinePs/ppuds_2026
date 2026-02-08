<?php

namespace Modules\Branch\Transformers\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class BranchWorkingHourResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'day'        => $this->day,
            'day_name'   => $this->day_name,
            'is_closed'  => (bool) $this->is_closed,
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null, // تنسيق الوقت HH:MM
            'end_time'   => $this->end_time ? substr($this->end_time, 0, 5) : null,
        ];
    }
}

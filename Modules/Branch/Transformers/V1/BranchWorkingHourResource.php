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
            'name'   => $this->day->getlabel(),
            'is_closed'  => (bool) $this->is_closed,
            'start_time' => $this->start_time,
            'end_time'   => $this->end_time,
        ];
    }
}

<?php

namespace Modules\PPUDS\Transformers\V1\StudentCompanyAssistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentCompanyAssistantStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_en' => $this->name_en,
            'email' => $this->email,
            'student_number' => $this->studentProfile?->student_number,
            'major' => $this->studentProfile?->major?->name,
        ];
    }
}

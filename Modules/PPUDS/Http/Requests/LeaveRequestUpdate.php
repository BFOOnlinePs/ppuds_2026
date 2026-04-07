<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PPUDS\Enums\LeaveRequestStatus;
use Modules\PPUDS\Enums\LeaveRequestType;

class LeaveRequestUpdate extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'student_company_id' => ['required', 'exists:' . config('ppuds.table_prefix') . 'students_companies,id'],
            'type'               => ['sometimes', 'in:' . implode(',', array_column(LeaveRequestType::cases(), 'value'))],
            'status'             => ['sometimes', 'in:' . implode(',', array_column(LeaveRequestStatus::cases(), 'value'))],
            'start_at'           => ['sometimes', 'date'],
            'end_at'             => ['sometimes', 'date', 'after_or_equal:start_at'],
            'reason'             => ['sometimes', 'string', 'max:1000'],
            'rejection_reason'   => ['sometimes', 'string', 'max:1000'],
            'company_approval'  => ['sometimes', 'in:' . implode(',', array_column(LeaveRequestStatus::cases(), 'value'))],
            'university_approval' => ['sometimes', 'in:' . implode(',', array_column(LeaveRequestStatus::cases(), 'value'))],
            'company_supervisor_comment' => ['sometimes', 'string', 'max:1000'],
            'university_supervisor_comment' => ['sometimes', 'string', 'max:1000'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}

<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PPUDS\Enums\LeaveRequestStatus;
use Modules\PPUDS\Enums\LeaveRequestType;

class LeaveRequestRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'student_company_id'        => ['required', 'exists:' . config('ppuds.table_prefix') . 'students_companies,id'],
            'type'                      => ['required', 'in:' . implode(',', array_column(LeaveRequestType::cases(), 'value'))],
            'start_at'                  => ['required', 'date'],
            'end_at'                    => ['required', 'date', 'after_or_equal:start_at'],
            'reason'                    => ['required', 'string', 'max:1000'],
            'attachment_file'           => ['nullable', 'file', 'max:2048'],
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

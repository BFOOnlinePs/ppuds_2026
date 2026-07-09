<?php

namespace Modules\PPUDS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PPUDS\Enums\CompanyStatus;
use Illuminate\Support\Facades\DB;

class CompanyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->route('company') ? $this->route('company')->id : null;
        $companyTranslationTable = config('ppuds.table_prefix') . 'company_translations';
        $branchTable = config('branch.table_prefix') . 'branches';

        return [
            'name'                  => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique($companyTranslationTable, 'name')->ignore($companyId, 'company_id')
            ],
            'website'               => ['nullable', 'url', 'max:255'],
            'description'           => ['nullable', 'string'],
            'company_category_id'   => ['sometimes', 'required', 'integer', 'exists:ppu_ds_company_categories,id'],
            'status'                => ['sometimes', 'required', 'integer', 'in:' . implode(',', array_column(CompanyStatus::cases(), 'value'))],
            'logo'                  => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],

            // الفروع (إذا تم إرسال مصفوفة الفروع، يجب التحقق منها)
            'branches'                => ['sometimes', 'required', 'array', 'min:1'],
            'branches.*.id'           => ['nullable', 'integer', 'exists:' . $branchTable . ',id'],
            'branches.*.name'         => ['required_with:branches', 'string', 'max:255'],
            'branches.*.email'        => [
                'nullable',
                'email',
                'max:255',
                'distinct',
                function ($attribute, $value, $fail) use ($branchTable) {
                    $index = explode('.', $attribute)[1];
                    $branchId = $this->input("branches.{$index}.id");

                    $query = DB::table($branchTable)->where('email', $value);
                    if ($branchId) {
                        $query->where('id', '!=', $branchId);
                    }

                    if ($query->exists()) {
                        $fail(__('The email has already been taken.'));
                    }
                }
            ],
            'branches.*.phone'        => ['nullable', 'string', 'max:50'],
            'branches.*.name'         => 'required_without:branches.*.id|string|max:255',
            'branches.*.country_id'   => 'required_without:branches.*.id|integer',
            'branches.*.city_id'      => 'required_without:branches.*.id|integer',

            'branches.*.latitude'     => 'sometimes|numeric',
            'branches.*.longitude'    => 'sometimes|numeric',

            // الأقسام
            'branches.*.departments'           => ['nullable', 'array'],
            'branches.*.departments.*.name'    => ['required_with:branches.*.departments', 'string', 'max:255'],
            'branches.*.departments.*.user_id' => ['required_with:branches.*.departments', 'integer', 'exists:users,id'],

            // ساعات العمل
            'branches.*.working_hours'              => ['nullable', 'array'],
            'branches.*.working_hours.*.day'        => ['required_with:branches.*.working_hours', 'integer'],
            'branches.*.working_hours.*.is_closed'  => ['required_with:branches.*.working_hours', 'boolean'],
            'branches.*.working_hours.*.start_time' => ['nullable', 'date_format:H:i'],
            'branches.*.working_hours.*.end_time'   => ['nullable', 'date_format:H:i', 'after:branches.*.working_hours.*.start_time'],
        ];
    }
}

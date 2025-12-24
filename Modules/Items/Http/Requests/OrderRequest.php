<?php

namespace Modules\Items\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\PaymentMethod;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $addonOptionsTable = config('items.table_prefix') . 'addon_options';

        return [
            'delivery_address' => ['nullable', 'numeric', 'max:255'],
            'contact_phone'    => ['nullable', 'string', 'max:20'],
            'coupon_code'      => ['nullable', 'string', 'exists:' . config('coupon.table_prefix') . 'coupons,code'],
            'branch_id'        => ['required', 'integer'],
            'payment_method'   => ['required', new \Illuminate\Validation\Rules\Enum(PaymentMethod::class)],
            'notes'            => ['nullable', 'string'],
            'delivery_type'    => ['required', new \Illuminate\Validation\Rules\Enum(DeliveryType::class)],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:items_products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],

            'items.*.addons'   => 'nullable|array',
            'items.*.addons.*.addon_option_id' => 'required|exists:' . $addonOptionsTable . ',id',
            'items.*.addons.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
        ];
    }
}

<?php

namespace Modules\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerAddressesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'label' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'address_details' => 'required|string',
            'contact_phone' => ['required', 'digits:10'],
            'delivery_instructions' => 'nullable|string',
            'is_default' => 'nullable|boolean',
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

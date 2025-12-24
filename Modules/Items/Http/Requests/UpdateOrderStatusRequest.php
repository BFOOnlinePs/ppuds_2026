<?php

namespace Modules\Items\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Core\Traits\ApiResponse;
use Modules\Items\Enums\OrderStatus;
use function Clue\StreamFilter\fun;

class UpdateOrderStatusRequest extends FormRequest
{
    use ApiResponse;
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status'    =>  ['required', 'integer', Rule::enum(OrderStatus::class)],
            'note'      =>  ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $order = $this->route('order');

            if (!$order) return;

            $newStatusValue = (int) $this->input('status');
            $newStatus = OrderStatus::tryFrom($newStatusValue);

            if (!$newStatus) return;

            if (!$order->status->canTransitionTo($newStatus)){
                $validator->errors()->add(
                    'status',
                    __('Invalid status transition from :current to :new', [
                        'current' => $order->status->getLabel(),
                        'new'     => $newStatus->getLabel()
                    ])
                );
            }
        });
    }

//    public function isValidStatusTransition(OrderStatus $currentStatus, OrderStatus $newStatus): bool
//    {
//        if ($currentStatus == $newStatus) return true;
//
//        if (in_array($currentStatus, [
//            OrderStatus::CANCELLED,
//            OrderStatus::REJECTED,
//            OrderStatus::RETURNED,
//            OrderStatus::DELIVERED,
//        ])) return false;
//
//        $transitions = [
//            OrderStatus::PENDING->value             => [OrderStatus::CONFIRMED->value, OrderStatus::REJECTED->value, OrderStatus::CANCELLED->value],
//            OrderStatus::CONFIRMED->value           => [OrderStatus::PREPARING->value],
//            OrderStatus::PREPARING->value           => [OrderStatus::READY_FOR_PICKUP->value, OrderStatus::OUT_FOR_DELIVERY->value],
//            OrderStatus::READY_FOR_PICKUP->value    => [OrderStatus::OUT_FOR_DELIVERY->value, OrderStatus::DELIVERED->value],
//            OrderStatus::OUT_FOR_DELIVERY->value    => [OrderStatus::DELIVERED->value, OrderStatus::RETURNED->value],
//        ];
//
//        return in_array($newStatus->value , $transitions[$currentStatus->value] ?? []);
//    }
}

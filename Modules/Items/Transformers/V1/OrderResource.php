<?php

namespace Modules\Items\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Transformers\V1\UserResource;
use Modules\Delivery\Transformers\V1\CustomerAdressesResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'branch_id'        => $this->branch_id,
            'order_number'     => $this->order_number,
            'sub_total'        => (float) $this->sub_total,
            'delivery_fee'     => (float) $this->delivery_fee,
            'total'            => (float) $this->total,
            'notes'            => $this->notes,
            'delivery_address' => $this->whenLoaded('deliveryAddress' , function(){
                return new CustomerAdressesResource($this->deliveryAddress);
            }),
            'contact_phone'    => $this->contact_phone,
            'status'           => $this->status,
            'payment_method'   => $this->payment_method,
            'payment_status'   => $this->payment_status,
            'delivery_type'    => $this->delivery_type,
            'created_at'       => $this->created_at->format('Y-m-d H:i:s'),
            'customer'         => new UserResource($this->whenLoaded('user')),
            'items'            => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }

    public static function allowedIncludes(): array
    {
        return ['user', 'items', 'items.product', 'items.addonOptions', 'deliveryAddress'];
    }

    public static function allowedFields(): array
    {
        return [];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('status'),
            AllowedFilter::exact('payment_method'),
            AllowedFilter::exact('payment_status'),
            AllowedFilter::exact('user_id'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            AllowedSort::field('id'),
            AllowedSort::field('total'),
            AllowedSort::field('created_at'),
        ];
    }
}

<?php

namespace Modules\Core\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Entities\User;
use Modules\Core\Traits\Concerns\SelectsFieldsFromApi;
use Modules\Items\Transformers\V1\OrderResource;
use Spatie\QueryBuilder\AllowedFilter;

class UserResource extends JsonResource
{
    use SelectsFieldsFromApi;
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'image'             => $this->getProfileImageUrlAttribute(),
            'point_balance'     => $this->getPointBalance(),
            'branch_id'         => $this->branch_id,
            'roles'             => $this->whenLoaded('roles'),
            'profile'           => $this->whenLoaded('studentProfile'),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }

    public static function allowedFields(): array
    {
        $table = (new User())->getTable();
        return array_map(fn($field) => $table . '.' . $field, (new User())->getFillable());
    }

    public static function allowedSorts(): array
    {
        return ['id', 'name', 'email', 'phone'];
    }

    public static function allowedFilters(): array
    {
        return ['id', 'name', 'email', 'phone',
            AllowedFilter::callback('role', function (Builder $query, $value) {
                $query->whereHas('roles', function (Builder $q) use ($value) {
                    $q->where('name', $value);
                });
            }),
            ];
    }

    public static function allowedIncludes(): array
    {
        return ['media', 'orders', 'roles', 'studentProfile'];
    }
}

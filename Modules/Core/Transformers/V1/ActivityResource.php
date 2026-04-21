<?php

namespace Modules\Core\Transformers\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Entities\User;
use Modules\Core\Traits\Concerns\SelectsFieldsFromApi;
use Spatie\QueryBuilder\AllowedFilter;

class ActivityResource extends JsonResource
{
    use SelectsFieldsFromApi;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,

            'properties' => $this->properties,

            'causer' => $this->whenLoaded('causer', function () {
                return [
                    'id' => $this->causer_id,
                    'type' => class_basename($this->causer_type),
                    'name' => $this->causer->name ?? null,
                ];
            }),

            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject_id,
                    'type' => class_basename($this->subject_type),
                    'data' => $this->subject,
                ];
            }),

            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    public static function allowedFields(): array
    {
        return ['id', 'log_name', 'description', 'event', 'properties', 'causer_id', 'causer_type', 'subject_id', 'subject_type', 'created_at'];
    }

    public static function allowedSorts(): array
    {
        return ['id', 'log_name', 'event', 'created_at'];
    }

    public static function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('log_name'),
            AllowedFilter::exact('event'),
            AllowedFilter::exact('causer_id'),
            AllowedFilter::exact('causer_type'),
            AllowedFilter::exact('subject_id'),
            AllowedFilter::exact('subject_type'),
            AllowedFilter::callback('created_at', function (Builder $query, $value) {
                $query->whereDate('created_at', $value);
            }),

            AllowedFilter::callback('company_id', function (Builder $query, $value) {
                $query->whereHasMorph('causer', [User::class], function (Builder $userQuery) use ($value) {

                    $userQuery->whereHas('studentCompanies', function (Builder $studentCompanyQuery) use ($value) {

                        $studentCompanyQuery->where('company_id', $value);

                    });
                });
            }),

            AllowedFilter::callback('company_supervisor', function (Builder $query, $value) {
                $query->where(function (Builder $query) use ($value) {

                    $query->whereHasMorph('causer', [User::class], function (Builder $userQuery) use ($value) {
                        $userQuery->whereHas('studentCompanies', function (Builder $studentCompanyQuery) use ($value) {
                            $studentCompanyQuery->whereHas('branch', function (Builder $branchQuery) use ($value) {
                                $branchQuery->whereHas('departments', function (Builder $departmentQuery) use ($value) {
                                    $departmentQuery->where('user_id', $value);
                                });
                            });
                        });
                    });

                    // $query->orWhere(function (Builder $q) use ($value) {
                    //     $q->where('causer_id', $value)
                    //         ->where('causer_type', User::class);
                    // });

                });
            }),
        ];
    }

    public static function allowedIncludes(): array
    {
        return ['causer', 'subject'];
    }
}

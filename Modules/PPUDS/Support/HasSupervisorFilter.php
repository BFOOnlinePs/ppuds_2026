<?php

namespace Modules\PPUDS\Support;

use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;

trait HasSupervisorFilter
{
    protected function supervisorSelectFilter(?string $registrationRelation = 'registration'): SelectFilter
    {
        return SelectFilter::make('supervisor_id')
            ->label(__('Supervisor'))
            ->options(fn (): array => $this->supervisorFilterOptions())
            ->searchable()
            ->preload()
            ->query(fn (Builder $query, array $data): Builder => $this->applySupervisorFilter($query, $data, $registrationRelation));
    }

    protected function supervisorFilterOptions(): array
    {
        return User::query()
            ->whereHas(
                'roles',
                fn (Builder $query): Builder => $query->whereIn('name', $this->supervisorFilterRoleNames())
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function companySupervisorFilterOptions(): array
    {
        return User::query()
            ->whereHas(
                'roles',
                fn (Builder $query): Builder => $query->where('name', UserRole::COMPANY_SUPERVISOR->value)
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function applySupervisorFilter(Builder $query, array $data, ?string $registrationRelation = 'registration'): Builder
    {
        if (blank($data['value'] ?? null)) {
            return $query;
        }

        $supervisorId = (int) $data['value'];

        if (blank($registrationRelation)) {
            return $query->where('supervisor_id', $supervisorId);
        }

        return $query->whereHas(
            $registrationRelation,
            fn (Builder $registrationQuery): Builder => $registrationQuery->where('supervisor_id', $supervisorId)
        );
    }

    protected function supervisorFilterRoleNames(): array
    {
        return [
            UserRole::PRACTICAL_TRAINING_SUPERVISOR->value,
            'Academic Supervisor',
            'University Supervisor',
        ];
    }
}

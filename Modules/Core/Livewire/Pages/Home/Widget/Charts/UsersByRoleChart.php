<?php

namespace Modules\Core\Livewire\Pages\Home\Widget\Charts;

use Spatie\Permission\Models\Role;

class UsersByRoleChart extends DashboardChartWidget
{
    protected static ?string $heading = 'Users By Role';

    protected static string $color = 'primary';

    public static function canView(): bool
    {
        return static::canAny(['User View List']);
    }

    protected function getData(): array
    {
        $roles = Role::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => __('Users'),
                    'data' => $roles->pluck('users_count')->all(),
                    'backgroundColor' => $this->chartColors($roles->count()),
                ],
            ],
            'labels' => $roles->pluck('name')->map(fn (string $role) => __($role))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

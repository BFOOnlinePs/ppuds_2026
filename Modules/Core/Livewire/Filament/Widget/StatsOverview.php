<?php

namespace Modules\Core\Livewire\Filament\Widget;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            BaseWidget\Stat::make('total_users', User::count())
                ->label(__('Total Users'))
                ->description(__('All users in system'))
                ->color('success'),

            BaseWidget\Stat::make('total_client', User::role('Customer')->count())
                ->label(__('Total Clients'))
                ->description(__('All clients in system'))
                ->color('primary'),

            BaseWidget\Stat::make('total_products', Product::count())
                ->label(__('Total Products'))
                ->description(__('All products in system'))
                ->color('warning'),

            BaseWidget\Stat::make('total_orders', Order::count())
                ->label(__('Total Orders'))
                ->description(__('All orders in system'))
                ->color('danger'),

        ];
    }
}

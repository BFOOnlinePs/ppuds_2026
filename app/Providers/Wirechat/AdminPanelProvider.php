<?php

namespace App\Providers\Wirechat;

use Wirechat\Wirechat\Panel;
use Wirechat\Wirechat\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
             ->id('admin')
             ->path('admin/asdasd')
             ->middleware(['web']);
    }
}

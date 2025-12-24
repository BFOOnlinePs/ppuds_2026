<?php

namespace Modules\Core\Livewire\Pages\Home\Widget;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Widgets\Widget;
use Livewire\Component;
use Modules\Core\Services\ModulePackageService;
use Nwidart\Modules\Facades\Module;

class HomeActionsWidget extends Widget implements HasInfolists
{
    use InteractsWithInfolists;

    protected int | string | array $columnSpan = 'full';

    protected static string $view = 'core::livewire.pages.home.widget.home-actions-widget';

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->state([])
            ->schema([
                Section::make('Welcome')
                    ->description('Welcome to the admin panel dashboard. Here you can find an overview of the system status and quick access to various features.')
            ]);
    }
}

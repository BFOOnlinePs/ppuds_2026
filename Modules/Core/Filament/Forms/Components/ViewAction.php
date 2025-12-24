<?php
namespace Modules\Core\Filament\Forms\Components;

use Filament\Tables\Actions\Action;

class ViewAction extends Action
{
protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('')
            ->size('xl')
            ->tooltip(__('View'))
            ->color('dark')
            ->icon('solar-eye-bold')
            ->extraAttributes([
                'class' => 'text-dark',
            ]);
    }
}

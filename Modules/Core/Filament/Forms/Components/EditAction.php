<?php
namespace Modules\Core\Filament\Forms\Components;

use Filament\Tables\Actions\Action;

class EditAction extends Action
{
protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('')
            ->size('xl')
            ->color('success')
            ->icon('solar-pen-new-square-bold-duotone')
            ->tooltip(__('Edit'))
            ->extraAttributes([
                'class' => 'text-success',
            ]);
    }
}

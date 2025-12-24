<?php

namespace Modules\Core\Filament\Forms\Components;

use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Masmerise\Toaster\Toaster;

class DeleteAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('')
            ->size('lg')
            ->icon('solar-trash-bin-minimalistic-bold')
            ->tooltip(__('Delete'))
            ->modalHeading(__('Do you really want to delete these records?'))
            ->modalDescription(__('Once these records are deleted, all of their resources and data will be permanently deleted. Before deleting this record, please download any data you wish to retain.'))
            ->modalSubmitActionLabel(__('Yes, delete record'))
            ->modalCancelActionLabel(__('Cancel'))
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalSubmitAction(fn($action) => $action->color('danger'))
            ->modalCancelAction(fn($action) => $action->color('gray'))
            ->modalIconColor('danger')
            ->requiresConfirmation()
            ->color('danger')
            ->extraAttributes([
                'class' => 'text-danger-600',
            ]);
    }
}

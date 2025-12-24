<?php

namespace Modules\Core\Filament\Forms\Components;

use Filament\Tables\Actions\CreateAction as ActionsCreateAction;

class CreateAction extends ActionsCreateAction
{
    //    protected string $view = 'core::filament.forms.components.textarea';

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->icon('heroicon-m-plus')
            ->extraAttributes([
                'class' => 'inline-flex items-center gap-2 font-semibold px-4 py-2
                            bg-primary text-white hover:bg-primary-dark
                            dark:bg-primary dark:hover:bg-primary
                            transition duration-200 rounded-lg',
            ]);
    }
}

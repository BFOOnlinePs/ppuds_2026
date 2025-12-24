<?php
namespace Modules\Core\Filament\Forms\Components;


class TextInput extends \Filament\Forms\Components\TextInput
{
//    protected string $view = 'core::filament.forms.components.text-input';

//    protected function setUp(): void
//    {
//        parent::setUp();
//
//        $this->extraInputAttributes([
//            'class' => 'form-input',
//        ]);
//    }

protected function setUp(): void
    {
        parent::setUp();

        $this
            ->extraAttributes([
                'class' => 'form-input',
            ]);
    }
}

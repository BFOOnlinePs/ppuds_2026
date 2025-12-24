<?php

/*************************************************
 * Copyright (c) 2024.
 * @Author: Shaker Awad <awadshaker74@gmail.com>
 * @Date: 6/23/24, 9:35 AM.
 * @Project: Jumla
 ************************************************/

namespace Modules\Core\Providers;

use App\View\Components\AppLayout;
use Dotswan\MapPicker\Facades\MapPicker;
use Filament\Actions\StaticAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Filament\Forms\Components\Flatpickr;
use Modules\Core\Services\Settings\ApplicationSettings;
use Modules\Core\Services\Settings\SettingRegistrar;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Filament\Support\Assets\Js;

class ConfigServiceProvider extends ServiceProvider
{
    public function register() {}

    public function boot()
    {
        FilamentAsset::register([
            Css::make('custom', resource_path('css/dashboard/admin-theme/custom.css')),
        ]);

        FilamentColor::register([
            'danger' => Color::Red,
            'gray' => Color::Zinc,
            'info' => Color::Blue,
            'primary' => '#EE7517',
            'secondary' => Color::Zinc,
            'success' => Color::Green,
            'warning' => Color::Amber,
        ]);

        TextInput::configureUsing(function (TextInput $textInput) {
            $textInput->extraInputAttributes([
                'class' => '',
            ]);
        });

        // Textarea::configureUsing(function (Textarea $textarea) {
        //     $textarea->extraAttributes([
        //         'class' => 'form-textarea',
        //     ]);
        // });

        TextColumn::configureUsing(function (TextColumn $textColumn) {
            $textColumn->extraAttributes([
                'class' => 'text-sm text-gray-900 dark:text-gray-100 ',
            ]);
        });

        BulkAction::configureUsing(function (BulkAction $action): void {
            if ($action->getName() === 'delete') {
                $action
                    ->modalHeading(__('Do you really want to delete these records?'))
                    ->modalDescription(__('Once these records are deleted, all of their resources and data will be permanently deleted. Before deleting this record, please download any data you wish to retain.'))
                    ->modalSubmitActionLabel(__('Yes, delete record'))
                    ->modalCancelActionLabel(__('Cancel'))
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalSubmitAction(fn($action) => $action->color('danger'))
                    ->modalCancelAction(fn($action) => $action->color('gray'))
                    ->modalIconColor('danger');
            }
        });

        Toggle::configureUsing(function (Toggle $toggle) {
            $toggle->onColor('primary');
        });

        Fieldset::configureUsing(function (Fieldset $fieldset) {
            $fieldset->extraAttributes([
                'class' => 'bg-gray-50',
            ]);
        });
        // TextColumn::configureUsing(function (TextColumn $column) {
        //     $column->extraAttributes([
        //         'class' => 'text-sm text-[#ff5733] dark:text-gray-100',
        //     ]);
        // });
    }
}

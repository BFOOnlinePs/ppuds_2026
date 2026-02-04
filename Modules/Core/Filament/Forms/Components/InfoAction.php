<?php

namespace Modules\Core\Filament\Forms\Components;

use Filament\Forms\Components\Grid;
use Filament\Infolists\Components\Grid as ComponentsGrid;
use Filament\Tables\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Illuminate\Database\Eloquent\Model;

class InfoAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'info';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('')
            ->icon('solar-info-circle-bold-duotone')
            ->color('text-primary')
            ->size('xl')
            ->tooltip(__('Info'))
            ->extraAttributes([
                'class' => 'text-primary'
            ])
            ->infolist([
                ComponentsGrid::make(3)
                    ->schema([
                        TextEntry::make('created_by')->label(__('Created By'))
                        ->formatStateUsing(function ($record) {
                            return $record->createdBy ? $record->createdBy->getUserDisplayHtmlAttribute() : __('Unknown');
                        })
                        ->html(),
                        TextEntry::make('created_at')->label(__('Created At')),
                        TextEntry::make('updated_at')->label(__('Updated At')),
                    ]),

                ViewEntry::make('activity_log')
                    ->label(__('Activity Log'))
                    ->view('core::components.activity-log-modal')
                    ->viewData(function ($record) {
                        return [
                            'activities' => $this->getActivities($record),
                        ];
                    }),
            ])
            ->modalHeading(fn ($record) => __('Information') . ' - ' . __($this->getRecordTitle($record)))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->modalWidth('4xl');
    }

    protected function getActivities(Model $record)
    {
        return $record->activities()
            ->with('causer')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? static::getDefaultName());
    }
}

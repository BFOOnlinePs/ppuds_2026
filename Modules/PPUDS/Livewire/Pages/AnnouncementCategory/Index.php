<?php

namespace Modules\PPUDS\Livewire\Pages\AnnouncementCategory;

use App\View\Components\AppLayout;
use Astrotomic\Translatable\Validation\Rules\TranslatableUnique;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\AnnouncementCategory;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => AnnouncementCategory::query())
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Announcement Category'))
                    ->modalWidth(MaxWidth::Medium)
                    ->form([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->rules([
                                        new TranslatableUnique(AnnouncementCategory::class, 'name')
                                    ])
                                    ->required(),
                            ])
                    ])
                    ->using(function ($data, $action) {
                        $this->authorize('Announcement Category Create');
                        $data['created_by'] = auth()->id();
                        $announcementCategory = AnnouncementCategory::create($data);
                        return $announcementCategory;
                    })
                    ->after(function () {
                        Toaster::success(__('Announcement category created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Announcement Category Create')),
            ])
            ->bulkActions([]);
    }

    protected function getTableFilters(): array
    {
        return [

        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Announcement Category Info')),
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->default($record->name)
                            ->disabled(),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('Announcement Category View')),
            EditAction::make('edit')
                ->modalHeading(__('Edit Announcement Category'))
                ->modalWidth('md')
                ->form(function ($record) {
                    return [
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->default($record->name)
                            ->required(),
                    ];
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['locale'] = app()->getLocale();
                    return $data;
                })
                ->visible(fn() => auth()->user()->can('Announcement Category Update'))
                ->action(function (array $data, $record) {
                    $record->update($data);
                    Toaster::success(__('Announcement category updated successfully'));
                }),
            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Announcement Category Delete');
                    $record->delete();
                    Toaster::success(__('Announcement category deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Announcement Category Delete')),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.announcement-category.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Announcement Categories'), 'url' => route('announcement-category.index')],
            ]
        ]);
    }
}

<?php

namespace Modules\PPUDS\Livewire\Pages\Major;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Services\PpuApiService;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Major::query())
            ->columns([
                TextColumn::make('reference_code')
                    ->label(__('Reference Code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([

                Action::make('sync_major')
                    ->label(__('Sync Major'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (PpuApiService $service) {
                        $status = $service->syncMajors();
                        if ($status) {
                            Toaster::success(__('Sync Major') . ' ' . ($status ? __('Success') : __('Failed')));
                        }
                    }),

                CreateAction::make('create')
                    ->label(__('Add Major'))
                    ->form([
                        TextInput::make('reference_code')
                            ->label(__('Reference Code')),
                        TextInput::make('name')
                            ->label(__('Name')),
                        Textarea::make('description')
                            ->label(__('Description')),
                    ])
                    ->action(function (array $data) {
                        $data['created_by'] = auth()->id();

                        $major = Major::create($data);

                        Toaster::success(__('Major created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Major Create'))
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('reference_code')
                ->label(__('Reference Code')),
            Filter::make('name')
                ->label(__('Name')),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->visible(fn() => auth()->user()->can('Major Delete'))
                    ->action(fn(Collection $records) => $records->each->delete()),
            ])
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Major Info')),
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->default($record->name)
                            ->disabled(),
                        TextInput::make('website')
                            ->label(__('Website'))
                            ->default($record->website)
                            ->disabled(),
                        TextInput::make('category.name')
                            ->label(__('Category'))
                            ->default($record->category->name)
                            ->disabled(),
                        Textarea::make('description')
                            ->default($record->description)
                            ->disabled(),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('Major View')),
            EditAction::make('edit')
                ->form(function (Major $record) {
                    return [
                        TextInput::make('reference_code')
                            ->label(__('Reference Code'))
                            ->required()
                            ->default($record->reference_code)
                            ->maxLength(255)
                            ->unique(Major::class, 'reference_code', ignoreRecord: true),

                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->default($record->name)
                            ->maxLength(255),

                        Textarea::make('description')
                            ->default($record->description)
                            ->label(__('Description')),
                    ];
                })
                ->action(function (Major $record, array $data) {
                    $record->update($data);
                    Toaster::success(__('Major updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('Major Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Major Delete');
                    $record->delete();
                    Toaster::success(__('Major deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Major Delete'))
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.major.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Companies List'), 'url' => route('majors.index')],
            ]
        ]);
    }
}

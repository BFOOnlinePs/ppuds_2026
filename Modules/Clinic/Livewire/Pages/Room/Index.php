<?php

namespace Modules\Clinic\Livewire\Pages\Room;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Masmerise\Toaster\Toaster;
use Modules\Clinic\Entities\Room;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Filament\Forms\Components\Toggle; // ✨ إضافة لاستخدام حقل الحالة

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Room::query()->with('createdBy' , 'translations'))
            ->heading(__('Rooms'))
            ->emptyStateHeading(__('No rooms found'))
            ->emptyStateDescription(__('Create a new room by clicking the button below'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('Add Room'))
                    ->visible(fn() => auth()->user()->can('Room Create'))
            ])
            ->columns([
                TextColumn::make('name')
                    ->searchable(
                        query:function (Builder $query, string $search): Builder {
                            return $query->whereTranslationLike('name', '%' . $search . '%');
                        }
                    )
                    ->label(__('Name')),
                TextColumn::make('description')
                    ->label(__('Description')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('locale')
                    ->label(__('Locale'))
                    ->getStateUsing(function ($record) {
                        return $record->translations->pluck('locale')->join(', ');
                    })
                    ->sortable(),
            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Room'))
                    ->form([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required(),
                                Textarea::make('description')
                                ->label(__('Description')),
                            ])
                    ])
                    ->action(function ($data){
                        $this->authorize('Room Create');
                        $data['created_by'] = auth()->user()->id;
                        Room::create($data);
                        Toaster::success(__('Room created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Room Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('name')
                ->form([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->placeholder(__('Search by name'))
                ])
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['name'])) {
                        $query->whereTranslationLike('name', '%' . $data['name'] . '%');
                    }
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Room Info')), // ✨ تم التعديل
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->default($record->name)
                                    ->disabled(),
                                Textarea::make('location') // ✨ تم التعديل
                                ->label(__('Location / Description'))
                                    ->default($record->location)
                                    ->disabled(),
                            ]),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('Room View')), // ✨ تم التعديل

            EditAction::make('edit')
                ->label('')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->default($record->name)
                                    ->required(),
                                Textarea::make('description')
                                ->label(__('Description'))
                                    ->default($record->description),
                                Toggle::make('status')
                                ->label(__('Status'))
                                    ->default($record->status->value),
                            ])
                    ]);
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['locale'] = app()->getLocale();
                    return $data;
                })
                ->action(function ($data , $record){
                    $this->authorize('Room Update');
                    $record->update($data);
                    Toaster::success(__('Room updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('Room Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Room Delete'); // ✨ تم التعديل
                    $record->delete();
                    Toaster::success(__('Room deleted successfully')); // ✨ تم التعديل
                })
                ->visible(fn() => auth()->user()->can('Room Delete')), // ✨ تم التعديل
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
                    ->visible(fn() => auth()->user()->can('Room Delete')), // ✨ تم التعديل
            ])
        ];
    }

    public function render()
    {
        return view('clinic::livewire.pages.room.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Rooms List'), 'url' => route('rooms.index')],
            ]
        ]);
    }
}

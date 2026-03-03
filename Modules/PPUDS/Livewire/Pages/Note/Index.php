<?php

namespace Modules\PPUDS\Livewire\Pages\Note;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Note;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Note::query()->where('user_id', auth()->id())->with(['user']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Note Title'))
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('note_date')
                    ->label(__('Date'))
                    ->date('Y-m-d')
                    ->icon('solar-calendar-date-linear')
                    ->sortable(),

                IconColumn::make('is_pinned')
                    ->label(__('Pinned'))
                    ->boolean()
                    ->trueIcon('solar-pin-bold')
                    ->falseIcon('solar-pin-linear')
                    ->trueColor('primary'),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters())
            ->actions($this->getTableActions())
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Note'))
                    ->url(route('notes.add')) // تأكد من تعريف Route بهذا الاسم
                    ->visible(fn() => auth()->user()->can('Note Create'))
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            // فلتر الملاحظات المثبتة
            Filter::make('is_pinned')
                ->label(__('Pinned Only'))
                ->query(fn (Builder $query) => $query->where('is_pinned', true)),

            // فلتر التاريخ
            Filter::make('note_date')
                ->form([
                    DatePicker::make('from')->label(__('From Date')),
                    DatePicker::make('until')->label(__('Until Date')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['from'], fn ($query, $date) => $query->whereDate('note_date', '>=', $date))
                        ->when($data['until'], fn ($query, $date) => $query->whereDate('note_date', '<=', $date));
                })
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete Selected'))
                    ->icon('solar-trash-bin-trash-bold')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
                    ->after(fn() => Toaster::success(__('Selected notes deleted successfully')))
                    ->visible(fn() => auth()->user()->can('Note Delete')),
            ])
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Note'))
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label(__('Title'))
                                ->disabled(),
                            TextInput::make('note_date')
                                ->label(__('Date'))
                                ->disabled(),
                            Select::make('category')
                                ->label(__('Category'))
                                ->options([
                                    'academic' => __('Academic'),
                                    'training' => __('Training'),
                                    'personal' => __('Personal'),
                                ])
                                ->disabled(),
                            Toggle::make('is_pinned')
                                ->label(__('Pinned'))
                                ->disabled(),
                            Textarea::make('content')
                                ->label(__('Content'))
                                ->columnSpanFull()
                                ->rows(5)
                                ->disabled(),
                        ])
                    ]);
                })
                ->modalSubmitAction(false),

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn(Note $record) => route('notes.edit', $record->id))
                ->visible(fn() => auth()->user()->can('Note Update')),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function ($record) {
                    $record->delete();
                    Toaster::success(__('Note deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Note Delete'))
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.note.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('My Notes'), 'url' => route('notes.index')],
            ]
        ]);
    }
}
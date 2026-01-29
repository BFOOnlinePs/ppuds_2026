<?php

namespace Modules\PPUDS\Livewire\Pages\Company;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use GuzzleHttp\Promise\Create;
use Illuminate\Database\Eloquent\Builder;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\StudnetProfile;
use View;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Company::query())
            ->columns([
                ImageColumn::make('logo')
                    ->label(__('Logo'))
                    ->getStateUsing(function ($record){
                        return $record->getImageAttribute();
                    }),

                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('website')
                    ->label(__('Website'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name'),
                TextColumn::make('status')
            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Company'))
                    ->url(route('companies.add'))
                    ->visible(fn() => auth()->user()->can('Company Create'))
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [

        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete()),
            ])
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Company Info')),
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
            ->visible(fn() => auth()->user()->can('Company Category View')),
            EditAction::make('edit')
                ->url(fn(Company $record) => route('companies.edit', $record->id))
                ->visible(fn() => auth()->user()->can('Company Category Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Company Category Delete');
                    $record->delete();
                    Toaster::success(__('Company category deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Company Category Delete'))
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.company.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Companies List'), 'url' => route('companies.index')],
            ]
        ]);
    }
}

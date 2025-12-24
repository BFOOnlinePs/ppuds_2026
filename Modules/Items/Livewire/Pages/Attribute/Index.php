<?php

namespace Modules\Items\Livewire\Pages\Attribute;

use App\View\Components\AppLayout;
use Closure;
use Dom\Attr;
use Dom\Text;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View as ComponentsView;
use Filament\Forms\Get;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use GuzzleHttp\Promise\Create;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\ActivityLogAction;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Items\Entities\Attribute;
use Modules\Items\Enums\AttributeType;
use View;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => Attribute::query()->with('createdBy', 'translations'))
            ->heading(__('Attributes'))
            ->columns([
                // ImageColumn::make('image')
                //     ->label(__('Image'))
                //     ->getStateUsing(function ($record) {
                //         return $record->getImageAttribute();
                //     })
                //     ->size(60),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->icon('solar-pen-new-square-bold')
                    ->url(function ($record) {
                        return route('attributes.edit', $record);
                    })
                    ->searchable(
                        query: function (Builder $query, string $search): Builder {
                            return $query->whereHas('translations', function ($q) use ($search) {
                                $q->where('name', 'like', '%' . $search . '%');
                            });
                        }
                    )
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('configuration')
                    ->label(__('Configure Attribute'))
                    ->state(function () {
                        return __('Configure Attribute');
                    })
                    ->action(
                        Action::make('configure')
                            ->modal()
                            ->modalHeading(__('Configure Attribute'))
                            ->modalWidth('xl')
                            ->form([
                                Repeater::make('attributeValues')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->columnSpanFull()
                                                    ->label(__('Name'))
                                                    ->required(),
                                                ColorPicker::make('color_code')
                                                    ->columnSpanFull()
                                                    ->label(__('Color'))
                                                    ->visible(fn($record) => $record->type === AttributeType::COLOR->value)
                                                    ->default('#000000')
                                                    ->rgba()
                                                    ->hex(),
                                                Textarea::make('description')
                                                    ->columnSpanFull()
                                                    ->label(__('Description')),
                                            ])
                                    ])
                                    ->default(function (?Model $record): array {
                                        if (!$record?->exists) return [];

                                        $currentLocale = app()->getLocale();

                                        return $record->attributeValues
                                            ->map(function ($value) use ($currentLocale) {
                                                $translation = $value->translate($currentLocale);

                                                if (!$translation) {
                                                    $translation = $value->translations->first();
                                                }

                                                return [
                                                    'id' => $value->id,
                                                    'color_code' => $value->color_code,
                                                    'name' => $translation?->name ?? '',
                                                    'description' => $translation?->description ?? '',
                                                ];
                                            })
                                            ->toArray();
                                    })
                                    ->addActionLabel(__('Add Attribute Value'))
                                    ->itemLabel(fn(array $state): ?string => $state['name'] ?? __('Untitled'))
                                    ->collapsible()
                                    ->cloneable()
                                    ->reorderable()
                            ])
                            ->action(function (array $data, $record) {
                                $locale = app()->getLocale();

                                $formIds = collect($data['attributeValues'])->pluck('id')->filter()->toArray();

                                $record->attributeValues()
                                    ->whereNotIn('id', $formIds)
                                    ->get()
                                    ->each->delete();

                                foreach ($data['attributeValues'] as $item) {
                                    $attributeValue = $record->attributeValues()->updateOrCreate(
                                        ['id' => $item['id'] ?? null],
                                        ['color_code' => $item['color_code'] ?? null]
                                    );

                                    $attributeValue->translateOrNew($locale)->name = $item['name'];
                                    $attributeValue->translateOrNew($locale)->description = $item['description'];
                                    $attributeValue->save();
                                }
                            })
                    ),

                TextColumn::make('type')
                    ->label(__('Type'))
                    ->getStateUsing(function ($record) {
                        return AttributeType::from($record->type)->label();
                    })
                    ->sortable(),

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
                    ->label(__('Add Attribute'))
                    ->url(route('attributes.add'))
                    ->visible(fn() => auth()->user()->can('Attribute Create'))
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * The table filters.
     *
     * @return array
     */
    /*******  923c272c-8fa7-4881-8754-4bd584d02cc1  *******/
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
            Filter::make('type')
                ->form([
                    Select::make('type')
                        ->label(__('Type'))
                        ->options(AttributeType::options())
                ])
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['type'])) {
                        $query->where('type', $data['type']);
                    }
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [

            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Attribute Info')),
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->default($record->name)
                                    ->disabled(),

                                TextInput::make('slug')
                                    ->label(__('Slug'))
                                    ->default($record->slug)
                                    ->disabled()
                            ]),
                        Grid::make(1)
                            ->schema([
                                Textarea::make('description')
                                    ->label(__('Description'))
                                    ->default($record->description)
                                    ->disabled(),
                            ]),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('Attribute View')),
            EditAction::make('edit')
                ->url(fn(Attribute $record) => route('attributes.edit', $record->id))
                ->visible(fn() => auth()->user()->can('Attribute Update')),

            DeleteAction::make('delete')
                ->action(fn($record) => $record->forceDelete())
                ->visible(fn() => auth()->user()->can('Attribute Delete')),
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

    public function render()
    {
        return view('items::livewire.pages.attribute.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Attributes List'), 'url' => route('attributes.index')],
            ]
        ]);
    }
}

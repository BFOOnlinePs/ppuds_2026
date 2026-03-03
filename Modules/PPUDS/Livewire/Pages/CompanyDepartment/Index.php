<?php

namespace Modules\PPUDS\Livewire\Pages\CompanyDepartment;

use App\View\Components\AppLayout;
use Astrotomic\Translatable\Validation\Rules\TranslatableUnique;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Set;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
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
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\StudentProfile;
use View;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => CompanyDepartment::query())
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
                    ->label(__('Add Company Department'))
                    ->modalWidth(MaxWidth::Medium)
                    ->form([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->rules([
                                        new TranslatableUnique(CompanyDepartment::class, 'name')
                                    ])
                                    ->required(),
                            ])
                    ])
                    ->using(function ($data , $action){
                        $this->authorize('Company Department Create');
                        $data['created_by'] = auth()->id();
                        $companyDepartment = CompanyDepartment::create($data);
                        return $companyDepartment;
                    })
                    ->after(function (){
                        Toaster::success(__('Company department created successfully'));
                    })
                    ->visible(fn() => auth()->user()->can('Company Department Create')),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->visible(fn() => auth()->user()->can('Company Department Delete')),
            ]);
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
                ->visible(fn() => auth()->user()->can('Company Department Info')),
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
            ->visible(fn() => auth()->user()->can('Company Department View')),
            EditAction::make('edit')
                ->modalHeading(__('Edit Label'))
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
                ->visible(fn() => auth()->user()->can('Company Department Update'))
                ->action(function (array $data, $record) {
                    $record->update($data);

                }),
            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Company Department Delete');
                    $record->delete();
                    Toaster::success(__('Company Department deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Company Department Delete')),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.company-department.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Companies Department List'), 'url' => route('company-category.index')],
            ]
        ]);
    }
}

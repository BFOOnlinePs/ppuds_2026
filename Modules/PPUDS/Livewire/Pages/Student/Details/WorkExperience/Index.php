<?php

namespace Modules\PPUDS\Livewire\Pages\Student\Details\WorkExperience;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Entities\WorkExperience;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public ?int $studentId = null;

    public function mount(?int $studentId = null)
    {
        $this->studentId = $studentId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(WorkExperience::where('user_id', $this->studentId))
            ->columns([
                TextColumn::make('company_name')
                    ->label(__('Company'))
                    ->searchable(),

                TextColumn::make('position')
                    ->label(__('Position'))
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label(__('Start Date'))
                    ->date('Y-m'),

                TextColumn::make('end_date')
                    ->label(__('End Date'))
                    ->date('Y-m'),

                TextColumn::make('is_current')
                    ->label(__('Current'))
                    ->badge(),
            ])
            ->actions($this->getTableActions())
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Work Experience'))
                    ->form(fn() => $this->getWorkExperienceForm())
                    ->action(function (array $data) {
                        $data['user_id'] = $this->studentId;
                        $data['created_by'] = auth()->id();
                        if (!empty($data['is_current'])) {
                            $data['end_date'] = null;
                        }
                        WorkExperience::create($data);
                        Toaster::success(__('Work experience added successfully'));
                    }),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('is_current')
                ->label(__('Current Position'))
                ->query(fn(Builder $query) => $query->where('is_current', true)),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->form(fn(WorkExperience $record) => [
                    Grid::make(2)->schema([
                        TextInput::make('company_name')
                            ->label(__('Company'))
                            ->default($record->company_name)
                            ->disabled(),

                        TextInput::make('position')
                            ->label(__('Position'))
                            ->default($record->position)
                            ->disabled(),

                        TextInput::make('sectorCategory.name')
                            ->label(__('Sector'))
                            ->default($record->sectorCategory?->name)
                            ->disabled(),

                        TextInput::make('location')
                            ->label(__('Location'))
                            ->default($record->location)
                            ->disabled(),

                        DatePicker::make('start_date')
                            ->label(__('Start Date'))
                            ->default($record->start_date)
                            ->disabled(),

                        DatePicker::make('end_date')
                            ->label(__('End Date'))
                            ->default($record->end_date)
                            ->disabled(),

                        Toggle::make('is_current')
                            ->label(__('Current Position'))
                            ->default($record->is_current)
                            ->disabled(),
                    ]),
                ])
                ->modalSubmitAction(false),

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->form(fn() => $this->getWorkExperienceForm())
                ->action(function (array $data, WorkExperience $record) {
                    if (!empty($data['is_current'])) {
                        $data['end_date'] = null;
                    }
                    $record->update($data);
                    Toaster::success(__('Work experience updated successfully'));
                }),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function (WorkExperience $record) {
                    $record->delete();
                    Toaster::success(__('Work experience deleted successfully'));
                })
        ];
    }

    protected function getWorkExperienceForm(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('company_name')
                    ->label(__('Company Name'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('position')
                    ->label(__('Position'))
                    ->required()
                    ->maxLength(255),

                Select::make('sector')
                    ->label(__('Sector'))
                    ->options(CompanyCategory::get()->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                TextInput::make('location')
                    ->label(__('Location'))
                    ->maxLength(255),

                DatePicker::make('start_date')
                    ->label(__('Start Date'))
                    ->required()
                    ->displayFormat('Y-m-d'),

                DatePicker::make('end_date')
                    ->label(__('End Date'))
                    ->displayFormat('Y-m-d')
                    ->hidden(fn($get) => $get('is_current')),

                Toggle::make('is_current')
                    ->label(__('Currently Working Here'))
                    ->live()
                    ->afterStateUpdated(fn($set) => $set('end_date', null)),

                Textarea::make('description')
                    ->label(__('Description'))
                    ->columnSpanFull()
                    ->rows(3),
            ]),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete Selected'))
                    ->icon('solar-trash-bin-trash-bold-duotone')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
                    ->after(fn() => Toaster::success(__('Selected records deleted successfully'))),
            ]),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.details.work-experience.index');
    }
}

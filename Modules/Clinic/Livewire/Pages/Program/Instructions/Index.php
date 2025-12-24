<?php

namespace Modules\Clinic\Livewire\Pages\Program\Instructions;

use App\View\Components\AppLayout;
use Filament\Forms\Components\RichEditor;
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
use Modules\Clinic\Entities\ProgramInstruction;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Filament\Forms\Components\Toggle;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table)
    {
        return $table
            ->query(fn() => ProgramInstruction::query()->with('createdBy' , 'translations'))
            ->heading('التعليمات')
            ->emptyStateHeading('لا توجد تعليمات')
            ->emptyStateDescription('أنشئ تعليمة جديدة بالضغط على الزر أدناه')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('إضافة تعليمة')
                    ->visible(fn() => auth()->user()->can('Program Instruction Create'))
            ])
            ->columns([
                TextColumn::make('name')
                    ->searchable(
                        query:function (Builder $query, string $search): Builder {
                            return $query->whereTranslationLike('name', '%' . $search . '%');
                        }
                    )
                    ->label('الاسم'),
                TextColumn::make('locale')
                    ->label('اللغة')
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
                    ->label('إضافة تعليمة')
                    ->form([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label('الاسم')
                                    ->required(),
                                RichEditor::make('description')
                                    ->label(__('Description'))
                            ])
                    ])
                    ->action(function ($data, $action){
                        $this->authorize('Program Instruction Create');
                        $data['created_by'] = auth()->user()->id;
                        ProgramInstruction::create($data);
                        Toaster::success('تم إنشاء التعليمة بنجاح');
                        $action->halt();
                        $action->getForm()->fill();
                    })
                    ->visible(fn() => auth()->user()->can('Program Instruction Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('name')
                ->form([
                    TextInput::make('name')
                        ->label('الاسم')
                        ->placeholder('البحث بالاسم')
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
                ->visible(fn() => auth()->user()->can('Program Instruction Info')),
            ViewAction::make('view')
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label('الاسم')
                                    ->default($record->name)
                                    ->disabled(),
                                RichEditor::make('description')
                                    ->label(__('Description'))
                                    ->default($record->description)
                                    ->disabled(),
                            ]),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('Program Instruction View')),

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
                                RichEditor::make('description')
                                    ->label(__('Description'))
                                    ->required()
                                    ->default($record->description),
                            ])
                    ]);
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['locale'] = app()->getLocale();
                    return $data;
                })
                ->action(function ($data , $record){
                    $this->authorize('Program Instruction Update');
                    $record->update($data);
                    Toaster::success('تم تحديث التعليمة بنجاح');
                })
                ->visible(fn() => auth()->user()->can('Program Instruction Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Program Instruction Delete');
                    $record->delete();
                    Toaster::success('تم حذف التعليمة بنجاح');
                })
                ->visible(fn() => auth()->user()->can('Program Instruction Delete')),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label('حذف')
                    ->requiresConfirmation()
                    ->action(fn(Collection $records) => $records->each->delete())
                    ->visible(fn() => auth()->user()->can('Program Instruction Delete')),
            ])
        ];
    }

    public function render()
    {
        return view('clinic::livewire.pages.program.instructions.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => 'Home', 'url' => route('home')],
                ['title' => 'Instructions List', 'url' => route('program.instructions.index')],
            ]
        ]);
    }
}

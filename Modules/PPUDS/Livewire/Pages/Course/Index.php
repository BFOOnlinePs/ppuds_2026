<?php

namespace Modules\PPUDS\Livewire\Pages\Course;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Enums\CourseType;

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => Course::query())
            ->columns([
                TextColumn::make('course_code')
                    ->label(__('Course Code'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('hours')
                    ->label(__('Hours')),
                TextColumn::make('course_type')
                    ->label(__('Course Type'))
                    ->badge()
            ])
            ->filters($this->getTableFilters())
            ->actions($this->getTableActions())
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Course'))
                    ->icon('heroicon-o-plus') // أيقونة هيرو ايكون للزر الخارجي
                    ->modalHeading(__('Create New Course'))
                    ->form([
                        Grid::make(2)->schema([
                            TextInput::make('course_code')
                                ->label(__('Course Code'))
                                ->prefixIcon('solar-qr-code-bold-duotone') // استخدام Solar داخل الموديل فقط
                                ->required()
                                ->unique(Course::class, 'course_code')
                                ->validationMessages([
                                    'unique' => __('This course code already exists.'),
                                    'required' => __('Please enter the course code.'),
                                ]),
                            TextInput::make('name')
                                ->label(__('Course Name'))
                                ->prefixIcon('solar-letter-opened-bold-duotone') // Solar Icon
                                ->required()
                                ->validationMessages([
                                    'required' => __('The course name is mandatory.'),
                                ]),
                            TextInput::make('hours')
                                ->label(__('Credit Hours'))
                                ->prefixIcon('solar-clock-circle-bold-duotone') // Solar Icon
                                ->numeric()
                                ->required(),
                            Select::make('course_type')
                                ->label(__('Course Type'))
                                ->prefixIcon('solar-layers-bold-duotone') // Solar Icon
                                ->options(CourseType::options())
                                ->required(),
                            Textarea::make('description')
                                ->label(__('Description'))
                                ->columnSpanFull(),
                        ])
                    ])
                    ->mutateFormDataUsing(function (array $data) {
                        $data['created_by'] = auth()->id();
                        return $data;
                    })
                    ->visible(fn() => auth()->user()->can('Course Create'))
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Course Info')),

            ViewAction::make('view')
                ->form(fn(Course $record) => [
                    Grid::make(2)->schema([
                        TextInput::make('course_code')->default($record->course_code)->disabled(),
                        TextInput::make('name')->default($record->name)->disabled(),
                        TextInput::make('hours')->default($record->hours)->disabled(),
                        Select::make('course_type')->options(CourseType::options())->default($record->course_type->value)->disabled(),
                        Textarea::make('description')->default($record->description)->disabled()->columnSpanFull(),
                    ])
                ])
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('Course View')),

            EditAction::make('edit')
                ->label('')
                ->modalHeading(__('Edit Course Information'))
                ->modalIcon('solar-pen-new-square-bold-duotone')
                ->form(fn(Course $record) => [
                    Grid::make(2)->schema([
                        TextInput::make('course_code')
                            ->label(__('Course Code'))
                            ->prefixIcon('solar-qr-code-bold-duotone')
                            ->required()
                            ->default($record->course_code)
                            ->unique(Course::class, 'course_code', ignoreRecord: true)
                            ->validationMessages([
                                'unique' => __('This course code already exists.'),
                                'required' => __('The course code is required.'),
                            ]),

                        TextInput::make('name')
                            ->label(__('Course Name'))
                            ->prefixIcon('solar-letter-opened-bold-duotone') // Solar Icon
                            ->required()
                            ->default($record->name)
                            ->validationMessages([
                                'required' => __('The course name is mandatory.'),
                            ]),

                        TextInput::make('hours')
                            ->label(__('Credit Hours'))
                            ->prefixIcon('solar-clock-circle-bold-duotone') // Solar Icon
                            ->numeric()
                            ->required()
                            ->default($record->hours),

                        Select::make('course_type')
                            ->label(__('Course Type'))
                            ->prefixIcon('solar-layers-bold-duotone') // Solar Icon
                            ->options(CourseType::options())
                            ->searchable()
                            ->required()
                            ->default($record->course_type->value),

                        Textarea::make('description')
                            ->label(__('Description'))
                            ->default($record->description)
                            ->columnSpanFull(),
                    ])
                ])
                ->action(function (Course $record, array $data) {
                    // إضافة ID المستخدم الذي قام بالتعديل إذا كان لديك حقل updated_by
                    $data['updated_by'] = auth()->id();

                    $record->update($data);
                    Toaster::success(__('Course updated successfully'));
                })
                ->visible(fn() => auth()->user()->can('Course Update')),

            DeleteAction::make('delete')
                ->action(function ($record) {
                    $this->authorize('Course Delete');
                    $record->delete();
                    Toaster::success(__('Course deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Course Delete'))
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('course_code')->label(__('Course Code')),
            Filter::make('name')->label(__('Name')),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete'))
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->visible(fn() => auth()->user()->can('Course Delete'))
                    ->action(fn(Collection $records) => $records->each->delete()),
            ])
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.course.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Courses List'), 'url' => route('courses.index')],
            ]
        ]);
    }
}

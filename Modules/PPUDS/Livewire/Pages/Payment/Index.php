<?php

namespace Modules\PPUDS\Livewire\Pages\Payment;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Payment;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Support\HasSupervisorFilter;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HasSupervisorFilter;
    use ScopesStudentCompanyVisibility;

    public array $filters = [];

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => Payment::query()->with(['studentCompany','studentCompany.registration.student' , 'supervisor', 'currency'])
                ->where($this->filters)
                ->whereHas(
                    'studentCompany',
                    fn (Builder $studentCompanyQuery): Builder => $this->applyStudentCompanyVisibilityScope($studentCompanyQuery)
                ))
            ->columns([
                TextColumn::make('studentCompany.registration.student.name')
                    ->label(__('Student'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('reference_id')
                    ->label(__('Reference ID'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('payment_value')
                    ->label(__('Payment Value')),

                TextColumn::make('currency.name')
                    ->label(__('Currency')),

                TextColumn::make('supervisor.name')
                    ->label(__('Supervisor')),

                TextColumn::make('student_notes')
                    ->label(__('Student Notes'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('company_notes')
                    ->label(__('Company Notes'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),

            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->actions($this->getTableActions())
            ->headerActions([
                \Modules\Core\Filament\Forms\Components\CreateAction::make('create')
                    ->label(__('Add Student Company'))
                    ->url(route('student-companies.add'))
                    ->visible(fn() => auth()->user()->can('StudentCompany Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            $this->supervisorSelectFilter(null),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('StudentCompany Info')),
            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->form(fn(StudentCompany $record) => [
                    Grid::make(2)->schema([
                        TextInput::make('student_name')
                            ->label(__('Student'))
                            ->default($record->registration?->student?->name)
                            ->disabled()
                            ->prefixIcon('solar-user-id-bold-duotone'),

                        TextInput::make('company_name')
                            ->label(__('Company'))
                            ->default($record->company?->name)
                            ->disabled()
                            ->prefixIcon('solar-city-bold-duotone'),

                        TextInput::make('status')
                            ->label(__('Status'))
                            ->default($record->status?->getLabel())
                            ->disabled()
                            ->prefixIcon('solar-flag-bold-duotone'),

                        TextInput::make('course_name')
                            ->label(__('Course'))
                            ->default($record->registration?->course?->name)
                            ->disabled()
                            ->prefixIcon('solar-book-bold-duotone'),
                    ]),
                ])
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('StudentCompany View')), // تأكد من اسم الصلاحية

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn(StudentCompany $record) => route('student-companies.edit', $record->id)) // تأكد من اسم الراوت
                ->visible(fn() => auth()->user()->can('StudentCompany Update')),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function ($record) {
                    $record->delete();
                    Toaster::success(__('Student company record deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('StudentCompany Delete')),
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
                    ->after(fn() => Toaster::success(__('Selected records deleted successfully')))
                    ->visible(fn() => auth()->user()->can('StudentCompany Delete')),
            ]),
        ];
    }

    public function render()
    {
        // تأكد من إنشاء ملف الـ Blade هذا
        return view('ppuds::livewire.pages.student-company.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Student Companies'), 'url' => route('student-companies.index')],
            ],
        ]);
    }
}

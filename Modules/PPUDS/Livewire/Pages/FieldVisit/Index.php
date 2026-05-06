<?php

namespace Modules\PPUDS\Livewire\Pages\FieldVisit;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Enums\SemesterType;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => FieldVisit::query()->with(['studentCompany.registration.student', 'supervisor']))
            ->columns([
                TextColumn::make('studentCompany.registration.student.name')
                    ->label(__('Student'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn (FieldVisit $record) => $record->studentCompany?->registration?->student?->email)
                    ->url(fn (FieldVisit $record) => route('field-visits.edit', $record)),

                TextColumn::make('supervisor.name')
                    ->label(__('Supervisor'))
                    ->searchable()
                    ->sortable()
                    ->icon('solar-user-id-bold-duotone'),

                TextColumn::make('visiting_place')
                    ->label(__('Visiting Place'))
                    ->searchable()
                    ->limit(30)
                    ->icon('solar-map-point-bold-duotone'),

                TextColumn::make('visit_date')
                    ->label(__('Visit Date'))
                    ->date('Y-m-d')
                    ->sortable()
                    ->icon('solar-calendar-date-bold-duotone'),

                TextColumn::make('visit_time')
                    ->label(__('Visit Time'))
                    ->time('H:i')
                    ->icon('solar-clock-circle-bold-duotone'),

                TextColumn::make('visit_duration')
                    ->label(__('Duration (Mins)'))
                    ->numeric()
                    ->sortable()
                    ->suffix(' '.__('Mins')),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('Y-m-d')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: \Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->actions($this->getTableActions())
            ->headerActions([
                \Modules\Core\Filament\Forms\Components\CreateAction::make('create')
                    ->label(__('Add Field Visit'))
                    ->url(route('field-visits.add'))
                    ->visible(fn () => auth()->user()->can('FieldVisit Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            \Filament\Tables\Filters\SelectFilter::make('student_id')
                ->label(__('Student'))
                ->relationship('studentCompany.student', 'name')
                ->searchable()
                ->preload(),

            \Filament\Tables\Filters\SelectFilter::make('supervisor_id')
                ->label(__('Supervisor'))
                ->relationship('supervisor', 'name')
                ->searchable()
                ->preload(),

            \Filament\Tables\Filters\SelectFilter::make('company')
                ->label(__('Company'))
                ->options(\Modules\PPUDS\Entities\Company::get()->pluck('name', 'id'))
                ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                    return $query->when($data['value'], function ($q, $companyId) {
                        $q->whereHas('studentCompany', fn ($scQ) => $scQ->where('company_id', $companyId));
                    });
                })
                ->searchable()
                ->preload(),

            \Filament\Tables\Filters\Filter::make('year')
                ->form([
                    TextInput::make('year')
                        ->label(__('Academic Year'))
                        ->numeric()
                        ->default(app(\Modules\PPUDS\Settings\GeneralSettings::class)->year)
                        ->placeholder(date('Y'))
                        ->prefixIcon('solar-calendar-search-bold-duotone'),
                ])
                ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                    return $query->when(
                        $data['year'],
                        fn (\Illuminate\Database\Eloquent\Builder $q, $year) => $q->whereHas('studentCompany.registration', fn ($regQ) => $regQ->where('year', $year))
                    );
                }),

            \Filament\Tables\Filters\Filter::make('semester_type')
                ->form([
                    \Filament\Forms\Components\Select::make('semester_type')
                        ->label(__('Semester Type'))
                        ->options(SemesterType::options())
                        ->default(app(\Modules\PPUDS\Settings\GeneralSettings::class)->semester_type->value)
                        ->prefixIcon('solar-bookmark-circle-bold-duotone'),
                ])
                ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                    return $query->when(
                        $data['semester_type'],
                        fn (\Illuminate\Database\Eloquent\Builder $q, $semester_type) => $q->whereHas('studentCompany.registration', fn ($regQ) => $regQ->where('semester', $semester_type))
                    );
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn () => auth()->user()->can('FieldVisit Info')),

            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->form(fn (FieldVisit $record) => [
                    Grid::make(2)->schema([
                        TextInput::make('student_name')
                            ->label(__('Student'))
                            ->default($record->studentCompany?->registration?->student?->name)
                            ->disabled()
                            ->prefixIcon('solar-user-id-bold-duotone'),

                        TextInput::make('supervisor_name')
                            ->label(__('Supervisor'))
                            ->default($record->supervisor?->name)
                            ->disabled()
                            ->prefixIcon('solar-user-id-bold-duotone'),

                        TextInput::make('visiting_place')
                            ->label(__('Visiting Place'))
                            ->default($record->visiting_place)
                            ->disabled()
                            ->prefixIcon('solar-map-point-bold-duotone'),

                        DatePicker::make('visit_date')
                            ->label(__('Visit Date'))
                            ->default($record->visit_date)
                            ->disabled()
                            ->prefixIcon('solar-calendar-date-bold-duotone'),

                        TimePicker::make('visit_time')
                            ->label(__('Visit Time'))
                            ->default($record->visit_time)
                            ->disabled()
                            ->prefixIcon('solar-clock-circle-bold-duotone'),

                        TextInput::make('visit_duration')
                            ->label(__('Duration'))
                            ->default($record->visit_duration)
                            ->disabled()
                            ->suffix(__('Minutes')),

                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->default($record->notes)
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
                ])
                ->modalSubmitAction(false)
                ->visible(fn () => auth()->user()->can('FieldVisit View')),

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn (FieldVisit $record) => route('field-visits.edit', $record->id))
                ->visible(fn () => auth()->user()->can('FieldVisit Update')),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function ($record) {
                    $record->delete();
                    Toaster::success(__('Field visit record deleted successfully'));
                })
                ->visible(fn () => auth()->user()->can('FieldVisit Delete')),
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
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->after(fn () => Toaster::success(__('Selected records deleted successfully'))),
            ]),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.field-visit.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Field Visits'), 'url' => route('field-visits.index')],
            ],
        ]);
    }
}
